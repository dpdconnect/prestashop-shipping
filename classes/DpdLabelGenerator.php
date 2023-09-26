<?php

namespace DpdConnect\classes;

/**
 * This file is part of the Prestashop Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2017  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use Db;
use Order;
use OrderCarrier;
use Tools;
use Address;
use Country;
use Currency;
use DbQuery;
use Product;
use LinkCore;
use Exception;
use ZipArchive;
use OrderDetail;
use FeatureValue;
use Configuration;
use DpdConnect\classes\JobRepo;
use DpdConnect\classes\Version;
use DpdConnect\classes\BatchRepo;
use DpdConnect\classes\enums\ParcelType;
use DpdConnect\classes\OrderResponseTransformer;
use DpdConnect\classes\Exceptions\InvalidRequestException;
use DpdConnect\classes\Exceptions\InvalidResponseException;
use DpdConnect\Sdk\ClientBuilder;
use DpdConnect\Sdk\Objects\MetaData;
use DpdConnect\Sdk\Objects\ObjectFactory;
use DpdConnect\Sdk\Exceptions\ApiException;
use DpdConnect\Sdk\Exceptions\AuthenticateException;
use DpdConnect\Sdk\Exceptions\DpdException;
use DpdConnect\Sdk\Exceptions\HttpException;
use DpdConnect\Sdk\Exceptions\InvalidArgumentHttpException;
use DpdConnect\Sdk\Exceptions\RequestException;
use DpdConnect\Sdk\Exceptions\ServerException;
use DpdConnect\Sdk\Exceptions\ShipmentStatusException;
use DpdConnect\Sdk\Exceptions\ShipmentValidationException;
use DpdConnect\Sdk\Exceptions\ValidationException;

class DpdLabelGenerator
{
    public $dpdClient;
    public $errors;
    public $dpdError;
    public $dpdParcelPredict;

    public function __construct()
    {
        $url = Configuration::get('dpdconnect_url');
        $username = Configuration::get('dpdconnect_username');
        $encryptedPassword = Configuration::get('dpdconnect_password');
        if ($encryptedPassword === null || $encryptedPassword === "") {
            throw new Exception('No credentials provided');
        }
        $password = DpdEncryptionManager::decrypt($encryptedPassword);
        $clientBuilder = new ClientBuilder($url, ObjectFactory::create(MetaData::class, [
            'webshopType' => Version::type(),
            'webshopVersion' => Version::webshop(),
            'pluginVersion' => Version::plugin(),
        ]));
        $this->dpdClient = $clientBuilder->buildAuthenticatedByPassword($username, $password);

        $this->dpdClient->getAuthentication()->setJwtToken(
            Configuration::get('dpdconnect_jwt_token') ?: null
        );

        $this->dpdClient->getAuthentication()->setTokenUpdateCallback(function ($jwtToken) {
            Configuration::updateValue('dpdconnect_jwt_token', $jwtToken);
            $this->dpdClient->getAuthentication()->setJwtToken($jwtToken);
        });

        $this->dpdError = new DpdError();
        $this->dpdParcelPredict = new DpdParcelPredict();
    }

    public function generateLabel($orderIds, $parcelCount, $return, $freshFreezeData = array())
    {
        if (isset($this->errors['LOGIN_8'])) {
            $this->errors['LOGIN_8'] = $this->dpdError->get('LOGIN_8');
        }
        $labelsForDirectDownload = [];
        $labelRequests = [];

        $bundledOrders = FreshFreezeHelper::bundleOrders($orderIds);

        // Create shipment for every dpd shipping type
        foreach ($bundledOrders as $orderId => $orders) {
            if (!$this->dpdParcelPredict->checkIfDpdSending($orderId) && !FreshFreezeHelper::ordersContainFreshFreezeProducts([$orderId])) {
                continue;
            }

            $result = self::getLabelOutOfDb($orderId, $return);
            if ($result) {
                foreach ($result as $label) {
                    $labelsForDirectDownload[] = [
                        'pdf' => $label['label'],
                        'mpsId' => $label['mps_id'],
                    ];
                }
                continue;
            }

            foreach ($orders as $shippingType => $orderProducts) {
                try {
                    $labelRequests[] = $this->generateShipmentInfo($orderId, $orderProducts, $parcelCount, $return, $freshFreezeData);
                } catch (InvalidRequestException $e) {
                    $this->errors['VALIDATION'] = 'Multiple parcels is only allowed for countries inside EU.';
                    return;
                }
            }
        }

        if (count($labelRequests)) {
            $asyncTreshold = (int) Configuration::get('dpdconnect_async_treshold');
            if ($asyncTreshold === 0 || $asyncTreshold > 10) {
                $asyncTreshold = 10;
            }
            $labelResponses = $this->requestLabels($labelRequests, $return);
            if ($this->errors) {
                return;
            }
            if (count($labelRequests) >= $asyncTreshold) {
                return; // User will be redirected to batch overview
            } else {
                foreach ($labelResponses as $labelResponse) {
                    $this->storeOrders($labelResponse, $return);
                    $labelsForDirectDownload[] = [
                        'pdf' => base64_decode($labelResponse['label']),
                        'mpsId' => $labelResponse['shipmentIdentifier'],
                    ];

                    $order = new Order($orderId);
                    $orderCarrier = new OrderCarrier($order->getIdOrderCarrier());
                    $orderCarrier->tracking_number = reset($labelResponse['parcelNumbers']);

                    $orderCarrier->save();
                }
            }
        }

        if (count($labelsForDirectDownload) > 1) {
            return $this->redirectToZipDownload($labelsForDirectDownload);
        }

        return $this->redirectToPdfDownload($labelsForDirectDownload[0]);
    }

    public function generateShipmentInfo($orderId, $orderProducts, $parcelCount, $return, $freshFreezeData)
    {
        if ($parcelCount === null || $parcelCount === false) {
            $parcelCount = 1;
        }
        $tempOrder = new Order($orderId);
        $orderDetails = OrderDetail::getList($orderId);
        $address = new Address((int)$tempOrder->id_address_delivery);
        $dpdShippingType = $orderProducts[0]['dpd_shipping_product'];
        $amountOfUniqueSKUs = array_unique(array_column($orderProducts, 'reference'));
        $amountOfUniqueSKUs = count($amountOfUniqueSKUs);

        $country = new Country($address->id_country);
        $multipleParcelsAllowed = $this->isPartOfSingleMarket($country->iso_code);
        if ($parcelCount > 1 && !$multipleParcelsAllowed) {
            throw new InvalidRequestException();
        }

        if (empty($address->phone)) {
            $phone = $address->phone_mobile;
        } else {
            $phone = $address->phone;
        }
        $country = new Country($address->id_country);
        $customer = $tempOrder->getCustomer();
        $productCode = 'CL';
        $weightTotal = 0;
        $saturdayDelivery = false;
        $orderType = 'consignment';

        foreach ($orderProducts as $orderProduct) {
            if ((float)$orderProduct['product_weight'] <= 0) {
                $orderProduct['product_weight'] = '5.0';
            }
            $weightTotal += ((float)$orderProduct['product_weight'] / 100) * (int)$orderProduct['product_quantity'];
        }
        $weightTotal *= 100;
        if ($this->dpdParcelPredict->checkIfSaturdayCarrier($orderId) && !$return) {
            $saturdayDelivery = true;
        }
        //TODO when plugin create's a shipper check if the order uses the predict sending.
        // Error reporting
        if (empty($orderId)) {
            $this->errors[] = $this->dpdError->get('ID_IS_NOT_SET', $orderId);
            //TODO create log that the order_id is not set.
        }
        if ($tempOrder->id_address_delivery == null) {
            $this->errors[] = $this->dpdError->get('ORDER_ID_DOES_NOT_EXIST', $orderId);
            //TODO create log that the order doesn't exist
        }
        //checks if the order's state is canceled.
        if ($tempOrder->current_state == 6) {
            $this->errors[] = $this->dpdError->get('CANCELED', $orderId);
            //TODO create log taht the order's state is cancelled
        }
        if ($weightTotal / $parcelCount >= 31.5 * 100) {
            $this->errors[] = $this->dpdError->get('WEIGHT_TO_HEAVY');
            //TODO create log that the weight is to big
        }

        if (!($address->address2 == null)) {
            $street = $address->address1 . ' ' . $address->address2;
        } else {
            $street = $address->address1;
        }

        if (($address->lastname != null) && ($address->firstname != null)) {
            $fullName = $address->firstname . ' ' .  $address->lastname;
        } elseif ($address->lastname == null) {
            $fullName = $address->firstname;
        } elseif ($address->firstname == null) {
            $fullName = $address->lastname;
        }

        if ($return) {
            $productCode = 'RETURN';
        }

        if (!$return && $dpdShippingType === FreshFreezeHelper::TYPE_FRESH) {
            $productCode = strtoupper(FreshFreezeHelper::TYPE_FRESH);
        }

        if (!$return && $dpdShippingType === FreshFreezeHelper::TYPE_FREEZE) {
            $productCode = strtoupper(FreshFreezeHelper::TYPE_FREEZE);
        }

        $shipment = [
            'orderId' => (string)$orderId,
            'sendingDepot' => Configuration::get('dpdconnect_depot'),
            'sender' => [
                'name1' => Configuration::get('dpdconnect_company'),
                'street' => Configuration::get('dpdconnect_street'),
                'country' => Configuration::get('dpdconnect_country'),
                'postalcode' => Configuration::get('dpdconnect_postalcode'),
                'city' => Configuration::get('dpdconnect_place'),
                'phoneNumber' => Configuration::get('PS_SHOP_PHONE'),
                'email' => Configuration::get('dpdconnect_email'),
                'commercialAddress' => true,
                'vatnumber' => Configuration::get('dpdconnect_vatnumber'),
                'eorinumber' => Configuration::get('dpdconnect_eorinumber'),
            ],
            'receiver' => [
                'name1' =>  $fullName,
                'street' => $street,
                'country' => $country->iso_code,
                'postalcode' => $address->postcode, // No spaces in zipCode!
                'city' => $address->city,
                'phoneNumber' => $phone,
                'email' => $customer->email,
                'commercialAddress' => false,
            ],
            'product' => [
                'productCode' => $productCode,
                'saturdayDelivery' => $saturdayDelivery,
                'homeDelivery' => $this->dpdParcelPredict->checkIfPredictCarrier($orderId) || $this->dpdParcelPredict->checkIfSaturdayCarrier($orderId),
                'ageCheck' => $this->getAgeCheck($tempOrder->getWsOrderRows())
            ],
        ];

        if ($this->dpdParcelPredict->checkIfPredictCarrier($orderId) || $this->dpdParcelPredict->checkIfSaturdayCarrier($orderId)) {
            $shipment['notifications'][] = [
                'subject' => 'predict',
                'channel' => 'EMAIL',
                'value' => $customer->email,
            ];
        }

        if (
            !FreshFreezeHelper::shippingTypeIsFreshOrFreeze($dpdShippingType) &&
            $this->dpdParcelPredict->checkifParcelCarrier($orderId)
        ) {
            $parcelShopID = $this->dpdParcelPredict->getParcelShopId($orderId);
            $shipment['product']['parcelshopId'] = $parcelShopID;
            $shipment['notifications'][] = [
                'subject' => 'parcelshop',
                'channel' => 'EMAIL',
                'value' => $customer->email,
            ];
        }

        $shipment['parcels'] = [];

        if (!$return && FreshFreezeHelper::shippingTypeIsFreshOrFreeze($dpdShippingType)) {
            $shipment['parcels'] = $this->generateFreshFreezeParcels(
                $orderProducts,
                ceil($weightTotal / $parcelCount) * 100,
                $freshFreezeData,
                $parcelCount
            );
        } else {
            $amountOfParcels = $parcelCount;
            // If Fresh or Freeze, create a return label for every unique SKU
            if ($return && FreshFreezeHelper::shippingTypeIsFreshOrFreeze($dpdShippingType)) {
                $amountOfParcels = $amountOfUniqueSKUs;
            }

            for ($x = 1; $x <= $amountOfParcels; $x++) {
                $parcelInfo = [
                    'customerReferences' => [
                        (string)$orderId
                    ],
                    'weight' => (int) ceil($weightTotal / $amountOfParcels) * 100,
                ];

                if ((bool)$return) {
                    $parcelInfo['returns'] = true;
                }

                array_push($shipment['parcels'], $parcelInfo);
            }
        }

        $currency = new Currency($tempOrder->id_currency);
        $shipment['customs'] = [
            'terms' => 'DAP',
            'totalCurrency' => $currency->iso_code,
        ];

        $totalAmount = 0;

        $customsLines = [];

        foreach ($orderProducts as $orderProduct) {
            $product = new Product($orderProduct['product_id']);
            $hsCode = $this->getHsCode($product);
            $customsValue = $this->getCustomsValue($product);
            $originCountry = $this->getCountryOfOrigin($product);
            $weight = (int) ceil($product->weight) * 100; // Default weight is 0.000000
            if ($weight === 0) {
                $weight = Configuration::get('dpdconnect_default_product_weight');
            }
            $amount = $customsValue * $orderProduct['product_quantity'];
            $totalAmount += $amount;

            $customsLines[] = [
                'description' => mb_strcut($orderProduct['product_name'], 0, 35),
                'harmonizedSystemCode' => $hsCode ? $hsCode : "",
                'originCountry' => $originCountry ? $originCountry : "",
                'quantity' => (int) $orderProduct['product_quantity'],
                'netWeight' => (int) $weight,
                'grossWeight' => (int) $weight,
                'totalAmount' => (float) ($amount),
            ];
        }

        $shipment['customs']['totalAmount'] = (float) $totalAmount;

        $consignor = [
            'name1' => Configuration::get('dpdconnect_company'),
            'street' => Configuration::get('dpdconnect_street'),
            'postalcode' => Configuration::get('dpdconnect_postalcode'),
            'city' => Configuration::get('dpdconnect_place'),
            'country' => Configuration::get('dpdconnect_country'),
            'commercialAddress' => true,
            'sprn' => Configuration::get('dpdconnect_spr'),
            'vatnumber' => Configuration::get('dpdconnect_vatnumber'),
            'eorinumber' => Configuration::get('dpdconnect_eorinumber'),
        ];

        $consignee = [
            'name1' => $fullName,
            'street' => $street,
            'postalcode' => $address->postcode,
            'city' => $address->city,
            'country' => $country->iso_code,
            'commercialAddress' => false,
            'sprn' => Configuration::get('dpdconnect_spr'),
        ];

        $shipment['customs']['customsLines'] = $customsLines;
        $shipment['customs']['consignee'] = $consignee;
        $shipment['customs']['consignor'] = $consignor;

        return $shipment;
    }

    public static function getLabelOutOfDb($orderId, $return = false)
    {
        $sql = new DbQuery();
        $sql->from('dpdshipment_label');
        $sql->select('*');
        $sql->where('order_id=' . pSQL($orderId) . ' AND retour = ' . pSQL((int)$return));

        $result = Db::getInstance()->executeS($sql);
        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    public static function countLabels($orderId, $return = false)
    {
        $databaseLabel = self::getLabelOutOfDb($orderId, $return);
        if ($databaseLabel) {
            $labelNumbers = unserialize($databaseLabel[0]['label_nummer']);
            $result = count($labelNumbers);
        } else {
            $result = 0;
        }
        return $result;
    }

    public static function deleteLabelFromDb($ordersId, $return)
    {
        foreach ($ordersId as $orderId) {
            Db::getInstance()->delete('dpdshipment_label', 'order_id=' . pSQL($orderId) . ' AND retour=' . pSQL((int)$return));

            $tempOrder = new Order($orderId);
            // so it empty the shipping number.
            $tempOrder->setWsShippingNumber('');

            $link = self::generateOrderViewUrl($orderId);
        }
        header('location: ' . $link);
        return true;
    }

    public static function generateOrderViewUrl($orderId)
    {
        $link = new LinkCore();
        $link = $link->getAdminLink('AdminOrders');
        $link = $link . '&id_order=' . $orderId . '&vieworder';

        return $link;
    }

    private function setErrorByException($e, $map)
    {
        foreach ($e->getErrorDetails()->validation as $detail) {
            list($orderId, $simplePath) = OrderResponseTransformer::parseDetail($map, $detail);
            $this->errors['DPD_ERRORS'] = 'Order ' . $orderId . ': ' . $detail['message'] . ' for ' . $simplePath;
        }

        foreach ($e->getErrorDetails()->errors as $detail) {
            if (!is_array($detail)) {
                $this->errors['DPD_VALIDATION'] = $detail;
            } else {
                try {
                    list($orderId, $simplePath) = OrderResponseTransformer::parseDetail($map, $detail);
                } catch (InvalidResponseException $e) {
                    $this->errors['DPD_VALIDATION'] = 'Something went wrong at DPD Connect';
                    continue;
                }
                if (!isset($detail['_embedded']['errors'][0]['message'])) {
                    $this->errors['DPD_VALIDATION'] = 'Order ' . $orderId . ': Something went wrong at DPD Connect';
                    continue;
                }
                $errorMessage = $detail['_embedded']['errors'][0]['message'] . ' for ' . $simplePath;
                $this->errors['DPD_VALIDATION'] = 'Order ' . $orderId . ': ' . $errorMessage;
            }
        }
    }

    private function storeOrders($labelResponse, $return)
    {
        $orderId = $labelResponse['orderId'];
        $mpsId = $labelResponse['shipmentIdentifier'];
        $labelNumbers = $labelResponse['parcelNumbers'];
        $pdf = base64_decode($labelResponse['label']);
        $tempOrder = new Order($orderId);
        // checks if the the order is being shipped or is delivered
        if ($tempOrder->current_state == 4 || $tempOrder->current_state == 5) {
            $shipped = 1;
        } else {
            $shipped = 0;
        }

        if ($return) {
            $return = 1;
        } else {
            $return = 0;
        }
        $serializedLabelNumbers = serialize($labelNumbers);
        Db::getInstance()->insert('dpdshipment_label', array(
            'mps_id' => $mpsId,
            'label_nummer' => $serializedLabelNumbers,
            'order_id' => (int)$orderId,
            'created_at' => (string)date('y-m-d h:i:s'),
            'shipped' => $shipped,
            'label' => addslashes($pdf),
            'retour' => $return,
        ));
        return true;
    }

    private function requestLabels($labelRequests, $return)
    {
        $request = [
            'printOptions' => [
                'printerLanguage' => 'PDF',
                'paperFormat' => 'A4',
                'verticalOffset' => 0,
                'horizontalOffset' => 0,
            ],
            'createLabel' => true,
            'shipments' => [],
        ];

        foreach ($labelRequests as $labelRequest) {
            $request['shipments'][] = $labelRequest;
        }

        $map = [];
        foreach ($labelRequests as $labelRequest) {
            $map[] = $labelRequest['orderId'];
        }

        $shipmentCount = count($request['shipments']);
        try {
            $asyncTreshold = (int) Configuration::get('dpdconnect_async_treshold');

            if ($asyncTreshold === 0 || $asyncTreshold > 10) {
                $asyncTreshold = 10;
            }

            if ($shipmentCount >= $asyncTreshold) {
                $asyncRequest = [
                    'callbackURI' => $this->createUrl(),
                    'label' => $request,
                ];
                $response = $this->dpdClient->getShipment()->createAsync($asyncRequest);
                $batchRepo = new BatchRepo();
                $batchId = $batchRepo->create($shipmentCount);
                $jobRepo = new JobRepo();

                if (isset($response->getContent()['message'])) {
                    throw new Exception($response->getContent()['message']);
                }

                if ($return) {
                    $parcelType = ParcelType::TYPERETURN;
                } else {
                    $parcelType = ParcelType::TYPEREGULAR;
                }
                foreach ($response->getContent() as $key => $job) {
                    $jobRepo->create($batchId, $job['jobid'], $request['shipments'][$key]['orderId'], $parcelType);
                }
                return $this->redirectToBatch($batchId);
            } else {
                $response = $this->dpdClient->getShipment()->create($request);
                if ($response->getStatus() === 200) {
                    return $response->getContent()['labelResponses'];
                }
            }
        } catch (DpdException $e) {
            return $this->setErrorByException($e, $map);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($message === '') {
                $message = 'DPD Connect Error';
            }
            $this->errors['DPD_CONNECT_ERROR'] = $message;
        }
    }

    public static function createUrl()
    {
        $baseUrl = Configuration::get('dpdconnect_callback_url');
        if ($baseUrl === "" || is_null($baseUrl)) {
            $baseUrl = Tools::getHttpHost(true) . __PS_BASE_URI__;
        }

        $url = $baseUrl . "index.php?fc=module&module=dpdconnect&controller=callback";

        return $url;
    }

    private function redirectToBatch($batchId)
    {
        $linkCore = new LinkCore();
        $url = $linkCore->getAdminLink('AdminDpdJobs') . sprintf('&submitFilterdpd_jobs=0&batch_id=%s#dpd_jobs', $batchId);
        return Tools::redirectAdmin($url);
    }

    public function redirectToPdfDownload($label)
    {
        $pdf = $label['pdf'];
        header("Content-type:application/pdf");
        header('Content-disposition: inline; filename="dpd-label-' . date("Ymdhis") . '.pdf"');
        echo $pdf;
    }

    private function redirectToZipDownload($labelsForDirectDownload)
    {
        $zip = new ZipArchive();
        $zipfile = tempnam(sys_get_temp_dir(), "zip");
        $res = $zip->open($zipfile, ZipArchive::CREATE);
        foreach ($labelsForDirectDownload as $item) {
            $fileName = $item['mpsId'] . '.pdf';
            $pdf = $item['pdf'];
            $zip->addFromString($fileName, $pdf);
        }

        $zip->close();
        if (empty($this->errors)) {
            header("Content-Type: application/zip");
            header('Content-Disposition: attachment; filename="dpd-label-' . date("Ymdhis") . '.zip"');

            echo file_get_contents($zipfile);
            unlink($zipfile);
            die;
        }
        unlink($zipfile);
    }

    private function getHsCode($product)
    {
        // First check if a feature is set for HS Codes
        $hsFeatureId = Configuration::get('dpdconnect_hs_code_feature');
        if ($hsFeatureId) {
            $productFeatures = $product->getFeatures();
            $productHsFeature = array_filter($productFeatures, function ($feature) use ($hsFeatureId) {
                return $feature['id_feature'] === $hsFeatureId;
            });

            $hsCode = new FeatureValue(current($productHsFeature)['id_feature_value']);

            if ($hsCode->value) {
                // Feature may contain multiple languages. Just picking the first one.
                return current($hsCode->value);
            }
        }

        // Next check if a custom mapping is set
        $sql = new DbQuery();
        $sql->from('dpd_product_attributes');
        $sql->select('hs_code');
        $sql->where('product_id = ' . $product->id);
        $result = Db::getInstance()->getValue($sql);

        if ($result) {
            return $result;
        }

        // Lastly, use the default HS Code as configured in DPD Connect configurations
        return Configuration::get('dpdconnect_default_product_hcs');
    }

    private function getAgeCheck($orderRows)
    {
        foreach ($orderRows as $orderRow) {
            $productId = $orderRow['product_id'];
            $product = new Product($productId);

            $cooFeatureId = Configuration::get('dpdconnect_age_check');
            if ($cooFeatureId) {
                $productFeatures = $product->getFeatures();
                $productCooFeature = array_filter($productFeatures, function ($feature) use ($cooFeatureId) {
                    return $feature['id_feature'] === $cooFeatureId;
                });

                $ageCheck = new FeatureValue(current($productCooFeature)['id_feature_value']);
                if ($ageCheck->value) {
                    // Feature may contain multiple languages. Just picking the first one.
                    return true;
                }
            }

            $default = Configuration::get('dpdconnect_age_check_attribute');
            if ($default) {
                return $default;
            }

            // Next check if a custom mapping is set
            $sql = new DbQuery();
            $sql->from('dpd_product_attributes');
            $sql->select('age_check');
            $sql->where('product_id = ' . $product->id);
            $result = Db::getInstance()->getValue($sql);

            if ($result) {
                return $result == true;
            }
        }

        return false;
    }

    private function getCountryOfOrigin($product)
    {
        // First check if a feature is set for HS Codes
        $cooFeatureId = Configuration::get('dpdconnect_country_of_origin_feature');
        if ($cooFeatureId) {
            $productFeatures = $product->getFeatures();
            $productCooFeature = array_filter($productFeatures, function ($feature) use ($cooFeatureId) {
                return $feature['id_feature'] === $cooFeatureId;
            });

            $countryOfOrigin = new FeatureValue(current($productCooFeature)['id_feature_value']);
            if ($countryOfOrigin->value) {
                // Feature may contain multiple languages. Just picking the first one.
                return current($countryOfOrigin->value);
            }
        }

        $default = Configuration::get('dpdconnect_default_product_country_of_origin');
        if ($default) {
            return $default;
        }

        // Next check if a custom mapping is set
        $sql = new DbQuery();
        $sql->from('dpd_product_attributes');
        $sql->select('country_of_origin');
        $sql->where('product_id = ' . $product->id);
        $result = Db::getInstance()->getValue($sql);

        if ($result) {
            return $result;
        }

        // Last resort, return the country of the webshops address
        return Configuration::get('dpdconnect_country');
    }

    private function getCustomsValue($product)
    {
        // First check if a feature is set for HS Codes
        $cvFeatureId = Configuration::get('dpdconnect_customs_value_feature');
        if ($cvFeatureId) {
            $productFeatures = $product->getFeatures();
            $productCvFeature = array_filter($productFeatures, function ($feature) use ($cvFeatureId) {
                return $feature['id_feature'] === $cvFeatureId;
            });

            $customsValue = new FeatureValue(current($productCvFeature)['id_feature_value']);
            if ($customsValue->value) {
                // Feature may contain multiple languages. Just picking the first one.
                return current($customsValue->value);
            }
        }

        // Next check if a custom mapping is set
        $sql = new DbQuery();
        $sql->from('dpd_product_attributes');
        $sql->select('customs_value');
        $sql->where('product_id = ' . $product->id);
        $result = Db::getInstance()->getValue($sql);

        if ($result) {
            return $result;
        }

        return $product->price;
    }

    private function isPartOfSingleMarket($iso2)
    {
        $countries = $this->dpdClient->getCountries()->getList();
        if ($key = $this->lookupCountry($iso2)) {
            return $countries[$key]['singleMarket'];
        }

        return false;
    }

    private function lookupCountry($iso2)
    {
        $countries = $this->dpdClient->getCountries()->getList();
        return array_search(strtoupper($iso2), array_column($countries, 'country'), true);
    }

    private function generateFreshFreezeParcels($orderProducts, $weight, $freshFreezeData, $parcelCount)
    {
        $parcels = [];

        foreach ($orderProducts as $orderProduct) {
            $orderId = (int)$orderProduct['id_order'];
            $expirationDate = $freshFreezeData[$orderId][$orderProduct['product_id']]['expiration_date'];
            $expirationDate = str_replace('-', '', $expirationDate);

            for ($i = 0; $i < $parcelCount; $i++) {
                $parcels[] = [
                    'customerReferences' => [
                        (string)$orderProduct['id_order'],
                        $orderProduct['reference']
                    ],
                    'weight' => (int) $weight,
                    'goodsExpirationDate' => (int)$expirationDate,
                    'goodsDescription' => mb_strcut($freshFreezeData[$orderId][$orderProduct['product_id']]['carrier_description'], 0, 30)
                ];
            }
        }

        return $parcels;
    }
}
