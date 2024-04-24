<?php

namespace DpdConnect\Service;

class FreshFreezeService
{
    const DEFAULT_SHIPPING_TYPE = 'default';
    private \DpdConnect\classes\Connect\Product $connectProduct;

    public function __construct()
    {
        $this->connectProduct = new \DpdConnect\classes\Connect\Product();
    }

    public function isFreshFreezeAvailable(): bool
    {
        $dpdProducts = $this->connectProduct->getList();

        return in_array(['fresh', 'freeze'], array_column($dpdProducts, 'type'));
    }

    public function getAllowedShippingTypes(): array
    {
        $allowedShippingTypes = [self::DEFAULT_SHIPPING_TYPE];
        $dpdProducts = $this->connectProduct->getList();

        if (true === in_array('fresh', array_column($dpdProducts, 'type'))) {
            $allowedShippingTypes[] = 'fresh';
        }

        if (true === in_array('freeze', array_column($dpdProducts, 'type'))) {
            $allowedShippingTypes[] = 'freeze';
        }

        return $allowedShippingTypes;
    }
}
