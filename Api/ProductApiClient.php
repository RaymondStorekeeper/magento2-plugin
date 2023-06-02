<?php

namespace StoreKeeper\StoreKeeper\Api;

use StoreKeeper\StoreKeeper\Api\ApiClient;
use StoreKeeper\ApiWrapper\ModuleApiWrapperInterface;

class ProductApiClient extends ApiClient
{
    private const STOREKEEPER_PRODUCTS_MODULE_NAME = 'ProductsModule';

    /**
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    private function getProductModule(string $storeId): ModuleApiWrapperInterface
    {
        return $this->getModule(self::STOREKEEPER_PRODUCTS_MODULE_NAME, $storeId);
    }

    /**
     * @param string $storeId
     * @param string $countryId
     * @return array
     * @throws \Exception
     */
    public function getTaxRates(string $storeId, string $countryId): array
    {
        return $this->getProductModule($storeId)->listTaxRates(
            0,
            100,
            null,
            [
                [
                    'name' => 'country_iso2__=',
                    'val' => $countryId
                ]
            ]
        );
    }
}
