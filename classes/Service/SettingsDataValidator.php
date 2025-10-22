<?php

namespace DpdConnect\classes\Service;

use ConfigurationCore as Configuration;

class SettingsDataValidator
{
    /** @var string  */
    const DPD_SETTINGS_PREFIX = 'dpdconnect_';

    /** @var string  */
    const DPD_EMAIL                             = self::DPD_SETTINGS_PREFIX.'email';
    const DPD_COUNTRY                           = self::DPD_SETTINGS_PREFIX.'country';
    const DPD_POSTAL_CODE                       = self::DPD_SETTINGS_PREFIX.'postalcode';
    const DPD_CITY                              = self::DPD_SETTINGS_PREFIX.'place';
    const DPD_DEPOT                             = self::DPD_SETTINGS_PREFIX.'depot';
    const DPD_COMPANY                           = self::DPD_SETTINGS_PREFIX.'company';
    const DPD_STREET                            = self::DPD_SETTINGS_PREFIX.'street';
    const DPD_VAT_NUMBER                        = self::DPD_SETTINGS_PREFIX.'vatnumber';
    const DPD_DEFAULT_PRODUCT_HCS               = self::DPD_SETTINGS_PREFIX.'default_product_hcs';
    const DPD_DEFAULT_PRODUCT_COUNTRY_OF_ORIGIN = self::DPD_SETTINGS_PREFIX.'default_product_country_of_origin';

    /**
     * @return array
     */
    public static function validateDataSettings(): array
    {
        $result['email']                             = self::validateEmail();
        $result['country']                           = self::validateCountryCode();
        $result['postalcode']                        = self::validatePostalCode();
        $result['city']                              = self::validateCity();
        $result['depot_number']                      = self::validateDepot();
        $result['company']                           = self::validateCompany();
        $result['street']                            = self::validateStreet();
        $result['vatnumber']                         = self::validateVatNumber();
        $result['default_product_country_of_origin'] = self::validateDefaultProductCountryOfOriginCode();
        $result['harmonized_system_code']            = self::validateHarmonizedSystemCode();

        return array_filter(
            $result,
            fn($value) => $value !== ''
        );
    }

    /**
     * @param string|null $value
     * @param int $maxLength
     * @param int $minLength
     * @return string
     */
    private static function validateLength(
        ?string $value,
        int $maxLength,
        int $minLength = 0
    ): string {
        $translator = \Context::getContext()->getTranslator();
        static $domain = 'Modules.Dpdconnect.SettingsValidation';

        $error = '';

        if (empty($value)) {
            $error = $translator->trans('Field is empty.', [], $domain);
        } else if (mb_strlen($value) > $maxLength) {
            $error = sprintf($translator->trans('Value is too long – max %d characters.', [], $domain), $maxLength);
        } else if (mb_strlen($value) < $minLength) {
            $error = sprintf($translator->trans('Value is too short – min %d characters.', [], $domain), $minLength);
        }

        return $error;
    }



    /**
     * @param string|null $email
     * @return string
     */
    public static function validateEmail(?string $email = null): string
    {
        static $maxLength = 50;

        if($email === null) {
            $email = Configuration::get(self::DPD_EMAIL);
        }

        $error = self::validateLength($email, $maxLength);

        if (!str_contains($email, "@")){
            $error = 'Not a valid email format';
        }

        return $error;
    }

    /**
     * @param string|null $countryCode
     * @return string
     */
    public static function validateCountryCode(?string $countryCode = null): string
    {
        static $maxLength = 2;

        if($countryCode === null) {
            $countryCode = Configuration::get(self::DPD_COUNTRY);
        }

        return self::validateLength($countryCode, $maxLength);
    }

    /**
     * @param string|null $countryCode
     * @return string
     */
    public static function validateDefaultProductCountryOfOriginCode(?string $countryCode = null): string
    {
        static $maxLength = 2;

        if($countryCode === null) {
            $countryCode = Configuration::get(self::DPD_DEFAULT_PRODUCT_COUNTRY_OF_ORIGIN);
        }

        return self::validateLength($countryCode, $maxLength);
    }

    /**
     * @param string|null $postalCode
     * @return string
     */
    public static function validatePostalCode(?string $postalCode = null): string
    {
        static $maxLength = 9;

        if($postalCode === null) {
            $postalCode = Configuration::get(self::DPD_POSTAL_CODE);
        }

        return self::validateLength($postalCode, $maxLength);
    }

    /**
     * @param string|null $city
     * @return string
     */
    public static function validateCity(?string $city = null): string
    {
        static $maxLength = 35;

        if($city === null) {
            $city = Configuration::get(self::DPD_CITY);
        }

        return self::validateLength($city, $maxLength);
    }

    /**
     * @param string|null $depotNumber
     * @return string
     */
    public static function validateDepot(?string $depotNumber = null): string
    {
        static $maxLength = 4;
        static $minLength = 4;

        if($depotNumber === null) {
            $depotNumber = Configuration::get(self::DPD_DEPOT);
        }

        return self::validateLength($depotNumber, $maxLength, $minLength);
    }

    /**
     * @param string|null $company
     * @return string
     */
    public static function validateCompany(?string $company = null): string
    {
        static $maxLength = 35;

        if($company === null) {
            $company = Configuration::get(self::DPD_COMPANY);
        }

        return self::validateLength($company, $maxLength);
    }

    /**
     * @param string|null $street
     * @return string
     */
    public static function validateStreet(?string $street = null): string
    {
        static $maxLength = 40;

        if($street === null) {
            $street = Configuration::get(self::DPD_STREET);
        }

        return self::validateLength($street, $maxLength);
    }

    /**
     * @param string|null $vatNumber
     * @return string
     */
    public static function validateVatNumber(?string $vatNumber = null): string
    {
        static $maxLength = 20;

        if($vatNumber === null) {
            $vatNumber = Configuration::get(self::DPD_VAT_NUMBER);
        }

        return self::validateLength($vatNumber, $maxLength);
    }

    /**
     * @param string|null $defaultProductHcs
     * @return string
     */
    public static function validateHarmonizedSystemCode(?string $defaultProductHcs = null): string
    {
        static $maxLength = 10;

        if($defaultProductHcs === null) {
            $defaultProductHcs = Configuration::get(self::DPD_DEFAULT_PRODUCT_HCS);
        }

        return self::validateLength($defaultProductHcs, $maxLength);
    }
}
