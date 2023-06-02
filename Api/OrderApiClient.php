<?php

namespace StoreKeeper\StoreKeeper\Api;

use StoreKeeper\StoreKeeper\Api\ApiClient;
use StoreKeeper\ApiWrapper\ModuleApiWrapperInterface;

class OrderApiClient extends ApiClient
{
    private const STOREKEEPER_SHOP_MODULE_NAME = 'ShopModule';

    /**
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    public function getShopModule(string $storeId): ModuleApiWrapperInterface
    {
        return $this->getModule(self::STOREKEEPER_SHOP_MODULE_NAME, $storeId);
    }

    /**
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    public function getPaymentModule(string $storeId): ModuleApiWrapperInterface
    {
        return $this->getModule(self::STOREKEEPER_PAYMENT_MODULE_NAME, $storeId);
    }

    /**
     * @param string $storeId
     * @param string $storeKeeperId
     * @param array $refundPayments
     * @throws \Exception
     */
    public function refundAllOrderItems(string $storeId, string $storeKeeperId, array $refundPayments): void
    {
        $this->getShopModule($storeId)->refundAllOrderItems(['id' => $storeKeeperId, 'refund_payments' => $refundPayments]);
    }

    /**
     * @param string $storeId
     * @param mixed $storekeeperId
     * @return array
     * @throws \Exception
     */
    public function getStoreKeeperOrder(string $storeId, mixed $storekeeperId): array
    {
        return $this->getShopModule($storeId)->getOrder($storekeeperId);
    }

    /**
     * @param string $storeId
     * @param string $storeKeeperId
     * @return string|null
     * @throws \Exception
     */
    public function getOrderStatusPageUrl(string $storeId, string $storeKeeperId): ?string
    {
        return $this->getShopModule($storeId)->getOrderStatusPageUrl($storeKeeperId);
    }

    /**
     * @param string $storeId
     * @return mixed
     */
    public function getStoreInformation(string $storeId): mixed
    {
        return $this->getShopModule($storeId)->getShopSettingsForHooks();
    }

    /**
     * @param string $storeId
     * @param array $status
     * @param string $storeKeeperId
     * @throws \Exception
     */
    public function updateOrderStatus(string $storeId, array $status, string $storeKeeperId): void
    {
        $this->getShopModule($storeId)->updateOrderStatus(['status' => $status], $storeKeeperId);
    }

    /**
     * @param string $storeId
     * @param array $payload
     * @param string $storeKeeperId
     * @throws \Exception
     */
    public function updateOrder(string $storeId, array $payload, string $storeKeeperId): void
    {
        $this->getShopModule($storeId)->updateOrder($payload, $storeKeeperId);
    }

    /**
     * @param string $storeId
     * @param array $payload
     * @return array
     * @throws \Exception
     */
    public function getNewOrderWithReturn(string $storeId, array $payload): array
    {
        return $this->getShopModule($storeId)->newOrderWithReturn($payload);
    }
}
