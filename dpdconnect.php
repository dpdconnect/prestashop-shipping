<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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

require_once(_PS_MODULE_DIR_ . 'dpdconnect' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

use Doctrine\ORM\EntityManagerInterface;
use DpdConnect\classes\Connect\Connection;
use DpdConnect\classes\DpdProductHelper;
use DpdConnect\classes\DpdHelper;
use DpdConnect\classes\DpdCarrier;
use DpdConnect\classes\enums\JobStatus;
use DpdConnect\classes\DpdShippingList;
use DpdConnect\classes\DpdParcelPredict;
use DpdConnect\classes\DpdLabelGenerator;
use DpdConnect\classes\DpdEncryptionManager;
use DpdConnect\classes\DpdCheckoutDeliveryStep;
use DpdConnect\classes\DpdDeliveryOptionsFinder;
use DpdConnect\classes\Service\SettingsDataValidator;
use DpdConnect\Entity\ProductShippingInformation;
use DpdConnect\Form\Modifier\ProductFormModifier;
use DpdConnect\Sdk\Client;
use DpdConnect\Sdk\Exceptions\AuthenticateException;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;

class dpdconnect extends Module
{
    const VERSION = '2.1';

    public $twig;
    public $commandBus;
    public $dpdHelper;
    public $dpdCarrier;
    public $dpdParcelPredict;
    public $dpdProductHelper;

    /** @var EntityManagerInterface */
    private $entityManager;

    private $hooks = [
        // Admin
        'displayAdminOrderTabLink',
        'displayAdminOrderTabContent',
        'actionCarrierProcess',
        'actionDispatcher', // Hook for updating DPD Carriers
        'actionProductFormBuilderModifier', // Hook to add custom fields to admin product page
        'actionAfterUpdateProductFormHandler', // Hook to process custom fields on admin product page

        // Checkout
        'actionCheckoutRender',
        'displayAfterCarrier',

        'displayOrderConfirmation',
        'actionOrderGridDefinitionModifier',
        'displayBackOfficeHeader',
    ];


    public function __construct()
    {
        $this->dpdHelper = new DpdHelper();
        $this->dpdCarrier = new DpdCarrier();
        $this->dpdParcelPredict = new DpdParcelPredict();
        $this->dpdProductHelper = new DpdProductHelper();

        // Initialize basic stuff
        $container = SymfonyContainer::getInstance();
        if ($container !== null) {
            /** @var EntityManagerInterface $entityManager */
            $this->entityManager = $container->get('doctrine.orm.entity_manager');
        }

        // the information about the plugin.
        $this->version = self::VERSION;
        $this->name = "dpdconnect";
        $this->displayName = $this->l("DPD Connect");
        $this->author = "DPD Nederland B.V.";
        $this->tab = 'shipping_logistics';
        $this->limited_countries = ['be', 'lu', 'nl'];

        $this->ps_versions_compliancy = [
            'min' => '1.7.0',
            'max' => _PS_VERSION_
        ];
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DPD Connect');
        $this->description = $this->l('Shipping intergration with DPD Connect.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('dpdconnect')) {
            $this->warning = $this->l('No name provided');
        }
    }

    /**
     * this is triggered when the plugin is installed
     */
    public function install()
    {
        // Install Tabs
        $parent_tab = new Tab();

        $parent_tab->name[$this->context->language->id] = $this->l('DPD configuration');
        $parent_tab->class_name = 'AdminDpd';
        $parent_tab->id_parent = 0; // Home tab
        $parent_tab->module = $this->name;
        $parent_tab->add();

        $attributesTab = new Tab();
        $attributesTab->name[$this->context->language->id] = $this->l('DPD Product Attributes');
        $attributesTab->class_name = 'AdminDpdProductAttributes';
        $attributesTab->id_parent = $parent_tab->id;
        $attributesTab->module = $this->name;
        $attributesTab->add();

        $batchTab = new Tab();
        $batchTab->name[$this->context->language->id] = $this->l('Batches');
        $batchTab->class_name = 'AdminDpdBatches';
        $batchTab->id_parent = $parent_tab->id;
        $batchTab->module = $this->name;
        $batchTab->add();

        $jobTab = new Tab();
        $jobTab->name[$this->context->language->id] = $this->l('Jobs');
        $jobTab->class_name = 'AdminDpdJobs';
        $jobTab->id_parent = $parent_tab->id;
        $jobTab->module = $this->name;
        $jobTab->add();

        if (parent::install()) {
            Configuration::updateValue('dpd', 'dpdconnect');
            Configuration::updateValue('dpdconnect_parcel_limit', 12);
        }
        if (!$this->dpdHelper->installDB()) {
            PrestaShopLogger::addLog('[DPD] Could not install the database', 3);
            return false;
        }
        foreach ($this->hooks as $hookName) {
            if (!$this->registerHook($hookName)) {
                PrestaShopLogger::addLog('[DPD] Cannot register hook ' . $hookName, 3);
                return false;
            }
        }
        if (!$this->dpdHelper->update()) {
            PrestaShopLogger::addLog('[DPD] Update failed', 3);
            return false;
        }

        PrestaShopLogger::addLog('[DPD] Module installed correctly!', 1);

        return true;
    }

    /**
     * this is triggered when the plugin is uninstalled
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        } else {
            // Uninstall Tabs
            $moduleTabs = Tab::getCollectionFromModule($this->name);
            if (!empty($moduleTabs)) {
                foreach ($moduleTabs as $moduleTab) {
                    $moduleTab->delete();
                }
            }
            Configuration::updateValue('dpd', 'not installed');

            return true;
        }
    }

    /**
     * @return bool
     */
    private static function validateAccountCredentials(): bool
    {
        // We use these values because its representative of how the Connection class gets these values, if they are empty there is no point in doing the request
        $passToCheck = DpdEncryptionManager::decrypt(Configuration::get('dpdconnect_password'));
        $userToCheck = Configuration::get('dpdconnect_username');

        $missingCredentials = true;
        if($passToCheck && $userToCheck) {
            try {
                (new Connection)->getPublicJwtToken();
                return true;
            } catch (AuthenticateException $e) {
                return false;
            } catch (\DpdConnect\Sdk\Exceptions\HttpException $e) {
                return false;
            }
        }

        if($missingCredentials) {
            return false;
        }

        return false;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $connectusername = strval(Tools::getValue("dpdconnect_username"));
            $connectpassword = strval(Tools::getValue("dpdconnect_password"));
            if ($connectpassword == null) {
                $connectpassword = Configuration::get('dpdconnect_password');
            } else {
                $connectpassword = DpdEncryptionManager::encrypt($connectpassword);
            }
            $depot = strval(Tools::getValue("dpdconnect_depot"));
            $company = strval(Tools::getValue("company"));
            $street = strval(Tools::getValue("street"));
            $postalcode = strval(Tools::getValue("postalcode"));
            $place = strval(Tools::getValue("place")); // City
            $country = strval(Tools::getValue("country"));
            $email = strval(Tools::getValue("email") ?? '');
            $vatnumber = strval(Tools::getValue("vatnumber") ?? '');
            $eorinumber = strval(Tools::getValue("eorinumber") ?? '');
            $labelFormat = Tools::getValue('labelformat') ?? 'a4';
            $spr = strval(Tools::getValue("spr") ?? '');
            $mapsKey = Tools::getValue('maps_key') ?? '';
            $useDpdKey = Tools::getValue('use_dpd_key') ?? '1';
            $defaultProductHcs = Tools::getValue('default_product_hcs') ?? '';
            $defaultProductWeight = Tools::getValue('default_product_weight') ?? '';
            $defaultProductCountryOfOrigin = Tools::getValue('default_product_country_of_origin') ?? '';
            $countryOfOriginFeature = Tools::getValue('country_of_origin_feature') ?? '';
            $ageCheckAttribute = Tools::getValue('age_check_attribute') ?? '';
            $customsValueFeature = Tools::getValue('customs_value_feature') ?? '';
            $hsCodeFeature = Tools::getValue('hs_code_feature') ?? '';
            $connecturl = strval(Tools::getValue("dpdconnect_url") ?? '');
            $callbackUrl = Tools::getValue('callback_url') ?? '';
            $asyncTreshold = Tools::getValue('async_treshold') ?? '';
            $markStatus = Tools::getValue('mark_status') ?? '';
            $mergePdf   = Tools::getValue('merge_pdfs') ?? false;
            $defaultPackageType = Tools::getValue('dpdconnect_default_package_type') ?? '100050050';

            if (!(empty($company) || empty($street) || empty($postalcode) || empty($place) || empty($country) || empty($email))) {
                Configuration::updateValue('dpdconnect_maps_key', $mapsKey);
                Configuration::updateValue('dpdconnect_use_dpd_key', $useDpdKey);

                Configuration::updateValue('dpdconnect_username', $connectusername);
                if ($connectpassword) {
                    Configuration::updateValue('dpdconnect_password', $connectpassword);
                }

                // Depot Validation
                $error = SettingsDataValidator::validateDepot($depot);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_depot', $depot);
                } else {
                    $output .= $this->displayError($this->l('Depot Number - ' . $error));
                }

                // Company Validation
                $error = SettingsDataValidator::validateCompany($company);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_company', $company);
                } else {
                    $output .= $this->displayError($this->l('Company Name - ' . $error));
                }

                // Street Validation
                $error = SettingsDataValidator::validateStreet($street);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_street', $street);
                } else {
                    $output .= $this->displayError($this->l('Street - ' . $error));
                }

                // PostalCode Validation
                $error = SettingsDataValidator::validatePostalCode($postalcode);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_postalcode', $postalcode);
                } else {
                    $output .= $this->displayError($this->l('Postal Code - ' . $error));
                }

                // City Validation
                $error = SettingsDataValidator::validateCity($place);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_place', $place);
                } else {
                    $output .= $this->displayError($this->l('City - ' . $error));
                }

                // Country Validation
                $error = SettingsDataValidator::validateCountryCode($country);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_country', $country);
                } else {
                    $output .= $this->displayError($this->l('Country - ' . $error));
                }

                // Email Validation
                $error = SettingsDataValidator::validateEmail($email);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_email', $email);
                } else {
                    $output .= $this->displayError($this->l('E-Mail - ' . $error));
                }

                // VAT Validation
                $error = SettingsDataValidator::validateVatNumber($vatnumber);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_vatnumber', $vatnumber);
                } else {
                    $output .= $this->displayError($this->l('VAT Number - ' . $error));
                }

                // Default Product Country Origin Validation
                $error = SettingsDataValidator::validateDefaultProductCountryOfOriginCode($defaultProductCountryOfOrigin);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_default_product_country_of_origin', $defaultProductCountryOfOrigin);
                } else {
                    $output .= $this->displayError($this->l('Default Product Country of Origin - ' . $error));
                }

                // HCS Validation
                $error = SettingsDataValidator::validateHarmonizedSystemCode($defaultProductHcs);
                if (empty($error)) {
                    Configuration::updateValue('dpdconnect_default_product_hcs', $defaultProductHcs);
                } else {
                    $output .= $this->displayError($this->l('HCS Code - ' . $error));
                }

                Configuration::updateValue('dpdconnect_labelformat', $labelFormat);
                Configuration::updateValue('dpdconnect_eorinumber', $eorinumber);
                Configuration::updateValue('dpdconnect_spr', $spr);
                Configuration::updateValue('dpdconnect_default_product_weight', $defaultProductWeight);
                Configuration::updateValue('dpdconnect_age_check_attribute', $ageCheckAttribute);
                Configuration::updateValue('dpdconnect_customs_value_feature', $customsValueFeature);
                Configuration::updateValue('dpdconnect_hs_code_feature', $hsCodeFeature);
                Configuration::updateValue('dpdconnect_url', $connecturl);
                Configuration::updateValue('dpdconnect_callback_url', $callbackUrl);
                Configuration::updateValue('dpdconnect_async_treshold', $asyncTreshold);
                Configuration::updateValue('dpdconnect_mark_status', $markStatus);
                Configuration::updateValue('dpdconnect_merge_pdf_files', $mergePdf);
                Configuration::updateValue('dpdconnect_default_package_type', $defaultPackageType);

                $credentialsMessage = self::validateAccountCredentials();

                if ($credentialsMessage) {
                    $output .= $this->displayConfirmation($this->l('Settings updated!'));
                } else {
                    $output .= $this->displayError($this->l('Settings updated - but could not validate account credentials, please check your username and password'));
                }
            } else {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            }
        }

        $orderStatusses = [];
        $orderStatusses[] = [
            'id_order_state' => '',
            'name' => 'Disabled'
        ];
        $orderStatusses = array_merge($orderStatusses, (new OrderState())->getOrderStates(1));

        $formAccountSettings = [
            'legend' => [
                'title' => $this->l('Account Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('DPD-Connect username'),
                    'name' => 'dpdconnect_username',
                    'required' => true
                ],
                [
                    'type' => 'password',
                    'label' => $this->l('DPD-Connect password'),
                    'name' => 'dpdconnect_password',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('DPD-Connect depot'),
                    'name' => 'dpdconnect_depot',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Google Maps API key'),
                    'name' => 'maps_key',
                    'required' => false
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l("Use DPD's Google Maps API Key"),
                    'desc' => $this->l('These may be subject to rate limiting, high volume users should use their own Google keys.'),
                    'name' => 'use_dpd_key',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id'    => 'yes',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'no',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l("Label format"),
                    'name' => 'labelformat',
                    'required' => true,
                    'class' => 't',
                    'options' => [
                        'query' => [
                            [
                                "id_feature" => 'A4',
                                "position" => 1,
                                "id_lang" => 1,
                                "name" => "A4",
                            ],
                            [
                                "id_feature" => 'A6',
                                "position" => 2,
                                "id_lang" => 1,
                                "name" => "A6",
                            ],
                        ],
                        'id' => 'id_feature',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l("Change order status"),
                    'desc' => $this->l('Change order status after a label is created. If you do not want to change the order status, set to Disabled.'),
                    'name' => 'mark_status',
                    'required' => true,
                    'class' => 't',
                    'options' => [
                        'query' => $orderStatusses,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'required' => true,
                    'class' => 't',
                    'label' => $this->l('Default Package Type'),
                    'desc' => $this->l('Set the default package type'),
                    'name' => 'dpdconnect_default_package_type',
                    'options' => [
                        'query' => [
                            [
                                'default_package_type' => '100050050',
                                'name' => $this->l('Normal Parcel'),
                                "position" => 1,
                                "id_lang" => 1,
                            ],
                            [
                                'default_package_type' => '015010010',
                                'name' => $this->l('Small Parcel'),
                                "position" => 2,
                                "id_lang" => 1,
                            ],
                        ],
                        'id' => 'default_package_type',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l("Merge PDF files "),
                    'desc' => $this->l('With this option you can select if you want to get a merged PDF file or a zip file when using the bulk select when genereting labels.'),
                    'name' => 'merge_pdfs',
                    'required' => true,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id'    => 'yes',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'no',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                ],
            ],
        ];

        $formAdres = [
            'legend' => [
                'title' => $this->l('Shipping Address'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Company name'),
                    'name' => 'company',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Street + house number'),
                    'name' => 'street',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Postal Code'),
                    'name' => 'postalcode',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Place'),
                    'name' => 'place',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Country code'),
                    'desc' => $this->l('Use ISO 3166-1 alpha-2 codes (e.g. NL, BE, DE, FR, etc.)'),
                    'name' => 'country',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Email'),
                    'name' => 'email',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Vat Number'),
                    'name' => 'vatnumber',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Eori Number'),
                    'name' => 'eorinumber',
                    'required' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('HMRC number'),
                    'desc' => $this->l('Mandatory if the value of the parcel is ≤ £ 135.'),
                    'name' => 'spr',
                    'required' => false
                ],
            ],
        ];

        $features = [];
        $features[] = [
            'id_feature' => '',
            'name' => 'None'
        ];
        $features = array_merge($features, Feature::getFeatures($this->context->language->id));

        $productSettings = [
            'legend' => [
                'title' => $this->l('Product settings'),
            ],
            'description' => 'Configure what features or default will be used for products. If features or defaults are not a solution, use the "DPD Product Attributes" tab instead.',
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Default product weight'),
                    'name' => 'default_product_weight',
                    'required' => false
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Customs value Feature'),
                    'desc' => $this->l('Select the product feature where the customs value is defined. If features are not used for customs value, leave empty to use DPD Product attributes or regular product price.'),
                    'name' => 'customs_value_feature',
                    'options' => [
                        'query' => $features,
                        'id' => 'id_feature',
                        'name' => 'name',
                    ],
                    'required' => false
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Country of origin Feature'),
                    'desc' => $this->l('Select the product feature where the product of origin is defined. If features are not used for country of origin, leave empty.'),
                    'name' => 'country_of_origin_feature',
                    'options' => [
                        'query' => $features,
                        'id' => 'id_feature',
                        'name' => 'name',
                    ],
                    'required' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Default Country of Origin'),
                    'desc' => $this->l('Use ISO 3166-1 alpha-2 codes (e.g. NL, BE, DE, FR, etc.)'),
                    'name' => 'default_product_country_of_origin',
                    'required' => true
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Harmonized System Code Feature'),
                    'desc' => $this->l('Select the product feature where the Harmonized System Code is defined. If features are not used for harmonized system codes, leave empty.'),
                    'name' => 'hs_code_feature',
                    'options' => [
                        'query' => $features,
                        'id' => 'id_feature',
                        'name' => 'name',
                    ],
                    'required' => false
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Age check attribute'),
                    'desc' => $this->l('Select the attribute used for age check'),
                    'name' => 'age_check_attribute',
                    'options' => [
                        'query' => $features,
                        'id' => 'id_feature',
                        'name' => 'name',
                    ],
                    'required' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Default Harmonized System Code'),
                    'name' => 'default_product_hcs',
                    'required' => true
                ],
            ],
        ];

        $advancedSettings = [
            'legend' => [
                'title' => $this->l('Advanced settings'),
            ],
            'description' => 'Settings below can be left empty, they are used for development and debugging purposes.',
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('DPD-Connect url'),
                    'name' => 'dpdconnect_url',
                    'required' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Callback URL'),
                    'name' => 'callback_url',
                    'required' => false
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Async treshold'),
                    'name' => 'async_treshold',
                    'desc' => 'Max 10',
                    'placeholder' => '10',
                    'required' => false
                ],
            ],
        ];

        $submit = [
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ],
        ];

        return $output . $this->dpdHelper->displayConfigurationForm($this, $formAccountSettings, $formAdres, $productSettings, $advancedSettings, $submit);
    }

    public function hookActionCheckoutRender($params)
    {
        $this->context->controller->addCSS(_PS_MODULE_DIR_ . 'dpdconnect' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . '8dpdLocator.css', 'all');
    }

    public function hookDisplayAfterCarrier(array $params)
    {
        if ($this->context->cart->isVirtualCart()) {
            return;
        }
        $address = new Address($params['cart']->id_address_delivery);

        if (!$address->postcode) {
            return;
        }

        $scope = $this->context->smarty->createData(
            $this->context->smarty
        );

        $useDpdKey = Configuration::get('dpdconnect_use_dpd_key') == 1;

        $mapsKey = '';
        if (!$useDpdKey) {
            $mapsKey = Configuration::get('gmaps_key');
        }

        $link = new Link();
        $scope->assign([
            'baseUri' => __PS_BASE_URI__,
            'parcelshopId' => $this->dpdCarrier->getLatestCarrierByReferenceId($this->dpdProductHelper->getDpdParcelshopCarrierId()),
            'sender' => $this->context->cart->id_carrier,
            'shippingAddress' => sprintf('%s %s %s', $address->address1, $address->postcode, $address->country),
            'dpdPublicToken' => (new Connection())->getPublicJwtToken(),
            'shopCountryCode' => $this->context->language->iso_code,
            'mapsKey' => $mapsKey,
            'cookieParcelId' => $this->context->cookie->parcelId,
            'oneStepParcelshopUrl' => $link->getModuleLink('dpdconnect', 'OneStepParcelshop'),
            'dpdParcelshopMapUrl' => (Configuration::get('dpdconnect_url')) ? Configuration::get('dpdconnect_url') : Client::ENDPOINT . '/parcelshop/map/js',
        ]);

        $tpl = $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . 'dpdconnect' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . '8' . DIRECTORY_SEPARATOR . '_dpdLocator8.tpl',
            $scope
        );

        return $tpl->fetch();

    }

    // Define Order grid bulk actions
    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        // $params['definition'] is instance of \PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinition
        $params['definition']->getBulkActions()->add(
            (new SubmitBulkAction('shipping_list_dpd'))
                ->setName($this->l('Print DPD Shipping List'))
                ->setOptions([
                    // in most cases submit action should be implemented by module
                    'submit_route' => 'dpdconnect_bulk_actions_shipping_list_dpd',
                ])
        );

        $params['definition']->getBulkActions()->add(
            (new SubmitBulkAction('print_dpd_labels'))
                ->setName($this->l('Print DPD Labels'))
                ->setOptions([
                     // in most cases submit action should be implemented by module
                     'submit_route' => 'dpdconnect_bulk_actions_print_dpd_labels',
                 ])
        );

        $params['definition']->getBulkActions()->add(
            (new SubmitBulkAction('print_dpd_return_labels'))
                ->setName($this->l('Print DPD Return Labels'))
                ->setOptions([
                     // in most cases submit action should be implemented by module
                     'submit_route' => 'dpdconnect_bulk_actions_print_dpd_return_labels',
                 ])
        );
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        $orderId = Tools::getValue('id_order');
        $parcelShopId = $this->dpdParcelPredict->getParcelShopId($orderId);

        if ($this->dpdParcelPredict->checkIfDpdSending($orderId)) {
            $this->context->smarty->assign([
                'isDpdCarrier' => $this->dpdParcelPredict->checkifParcelCarrier($orderId),
                'dpdParcelshopId' => $parcelShopId,
                'number' => DpdLabelGenerator::countLabels($orderId),
            ]);
            return $this->display(__FILE__, '_adminOrderTab.tpl');
        }
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $orderId = Tools::getValue('id_order');
        $parcelShopData = $this->dpdParcelPredict->getParcelShopData($orderId);
        $parcelCarrier = $this->dpdParcelPredict->checkifParcelCarrier($orderId);

        if ($this->dpdParcelPredict->checkIfDpdSending($orderId)) {
            $link = new LinkCore();
            $urlGenerateLabel = $link->getAdminLink('AdminDpdLabels');
            $urlGenerateLabel = $urlGenerateLabel . '&ids_order[]=' . $orderId;

            $urlGenerateReturnLabel = $urlGenerateLabel . '&return=true';

            $this->context->smarty->assign([
                'parcelCarrier' => $parcelCarrier,
                'parcelShopId' => $parcelShopData['parcelshop_id'] ?? null,
                'parcelShopData' => json_decode($parcelShopData['parcelshop_data'] ?? '', true),
                'number' => DpdLabelGenerator::countLabels($orderId),
                'isInDb' => DpdLabelGenerator::getLabelOutOfDb($orderId),
                'urlGenerateLabel' => $urlGenerateLabel,
                'urlGenerateReturnLabel' => $urlGenerateReturnLabel,
                'isReturnInDb' => DpdLabelGenerator::getLabelOutOfDb($orderId, true),
                'deleteGeneratedLabel' => $urlGenerateLabel . '&delete=true',
                'deleteGeneratedRetourLabel' => $urlGenerateReturnLabel . '&delete=true',
                'selected_package_type' => Configuration::get('dpdconnect_default_package_type'),
            ]);
            return $this->display(__FILE__, '_adminOrderTabLabels.tpl');
        }
    }


    public function hookActionCarrierProcess($params)
    {
        if ((int)$params['cart']->id_carrier === (int)$this->dpdCarrier->getLatestCarrierByReferenceId($this->dpdProductHelper->getDpdParcelshopCarrierId())) {
            if (empty($this->context->cookie->parcelId) || $this->context->cookie->parcelId == '') {
                $this->context->controller->errors[] = $this->l('Please select a parcelshop');
            }
        }
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'];
        if ((int)$order->id_carrier === (int)$this->dpdCarrier->getLatestCarrierByReferenceId($this->dpdProductHelper->getDpdParcelshopCarrierId())) {
            if (!empty($this->context->cookie->parcelId) && !($this->context->cookie->parcelId == '')) {
                Db::getInstance()->insert('parcelshop', [
                    'order_id' => pSQL($order->id),
                    'parcelshop_id' => pSQL($params['cookie']->parcelId),
                    'parcelshop_data' => pSQL($params['cookie']->parcelshopData)
                ]);
                unset($this->context->cookie->parcelId);
                unset($this->context->cookie->parcelshopData);
            }
        }
    }

    public function renderJobColumn($jobData)
    {
        if (!$jobData) {
            return;
        }

        list($id, $status) = explode(',', $jobData);

        $link = new LinkCore();
        $url = $link->getAdminLink('AdminDpdJobs') . sprintf('&submitFilterdpd_jobs=0&job_id=%s#dpd_jobs', $id);
        return sprintf('<a class="dpdTagUrl" href=%s>%s</a>', $url, JobStatus::tag($status));

        return;
    }

    public function renderPdfReturnColumn($orderId)
    {
        return $this->renderPdfColumn($orderId, true);
    }

    public function renderPdfColumn($orderId, $return = false)
    {
        if (!$orderId) {
            return;
        }

        $link = new LinkCore();
        $url = $link->getAdminLink('AdminDpdLabels');
        $url = $url . '&ids_order[]=' . $orderId;
        if ($return) {
            $url .= '&return=true';
        }
        return sprintf('<a href=%s><span class="label dpdTag">PDF</span></a>', $url);
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/dpd.css', 'all');
    }

    // This hook gets called on every request within prestashop to check if the carriers need updating. Every 24 hours they will update
    public function hookActionDispatcher($params)
    {
        // Prevent calling updateDPDCarriers() to prevent duplicate carrier entries
        if (!empty($_POST)) {
            return;
        }

        try {
            $this->dpdProductHelper->updateDPDCarriers();
        } catch (Exception $exception) {
            PrestaShopLogger::addLog('Could not update DPD Carriers: ' . $exception->getMessage(), 3);
        }
    }

    // Hook for adding custom Fresh and Freeze product fields
    public function hookActionProductFormBuilderModifier($params)
    {
        $productFormModifier = $this->get(ProductFormModifier::class);
        $productId = (int) $params['id'];

        $productFormModifier->modify($productId, $params['form_builder']);
    }

    public function hookActionAfterUpdateProductFormHandler(array $params)
    {
        $productId = (int) $params['id'];
        $formData = $params['form_data'];

        $repository = $this->entityManager->getRepository(ProductShippingInformation::class);
        $productShippingInformation = $repository->findOneBy([
            'productId' => $productId,
        ]);

        $dpdShippingProduct = $formData['dpd']['dpd_shipping_product'];
        $dpdCarrierDescription = $formData['dpd']['dpd_carrier_description'];
        if (null === $productShippingInformation) {
            $productShippingInformation = new ProductShippingInformation();
            $productShippingInformation->setProductId($productId);
            $productShippingInformation->setDpdShippingProduct($dpdShippingProduct);
            $productShippingInformation->setDpdCarrierDescription($dpdCarrierDescription);
        } else {
            $productShippingInformation->setDpdShippingProduct($dpdShippingProduct);
            $productShippingInformation->setDpdCarrierDescription($dpdCarrierDescription);
        }

        $this->entityManager->persist($productShippingInformation);
        $this->entityManager->flush();
    }

    public function dpdCarrier()
    {
        return new DpdCarrier();
    }

    public function dpdDeliveryOptionsFinder($context, $translator, $objectPresenter, $priceFormatter)
    {
        return new DpdDeliveryOptionsFinder(
            $context,
            $translator,
            $objectPresenter,
            $priceFormatter
        );
    }

    public function dpdLabelGenerator()
    {
        return $this->get(DpdLabelGenerator::class);
    }

    public function dpdShippingList()
    {
        return new DpdShippingList();
    }

    /**
     * @return true
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }
}
