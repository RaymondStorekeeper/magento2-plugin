<?php

namespace StoreKeeper\StoreKeeper\Api;

use Magento\Sales\Api\Data\OrderInterface;
use StoreKeeper\ApiWrapper\ModuleApiWrapper;
use StoreKeeper\StoreKeeper\Api\ApiClient;
use StoreKeeper\StoreKeeper\Api\OrderApiClient;
use StoreKeeper\ApiWrapper\ModuleApiWrapperInterface;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use Psr\Log\LoggerInterface;

class PaymentApiClient extends ApiClient
{
    private const STOREKEEPER_PAYMENT_MODULE_NAME = 'PaymentModule';

    private OrderApiClient $orderApiClient;
    private LoggerInterface $logger;

    /**
     * PaymentApiClient constructor.
     * @param OrderApiClient $orderApiClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderApiClient $orderApiClient,
        LoggerInterface $logger
    ) {
        $this->orderApiClient = $orderApiClient;
        $this->logger = $logger;
    }

    /**
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    private function getPaymentModule(string $storeId): ModuleApiWrapperInterface
    {
        return $this->getModule(self::STOREKEEPER_PAYMENT_MODULE_NAME, $storeId);
    }

    /**
     * @param string $storeId
     * @param array $parameters
     * @return int
     * @throws \Exception
     */
    public function getNewWebPayment(string $storeId, array $parameters = []): int
    {
        return $this->getPaymentModule($storeId)->newWebPayment($parameters);
    }

    /**
     * @param string $storeId
     * @param int $storeKeeperId
     * @param array $paymentIds
     * @retrun void
     */
    public function attachPaymentIdsToOrder(string $storeId, int $storeKeeperId, array $paymentIds = []): void
    {
        $this->getShopModule($storeId)->attachPaymentIdsToOrder(['payment_ids' => $paymentIds], $storeKeeperId);
    }

    /**
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    private function getShopModule(string $storeId): ModuleApiWrapperInterface
    {
        return $this->orderApiClient->getShopModule($storeId);
    }

    /**
     * @param string $storeId
     * @return array
     * @throws \Exception
     */
    public function getListTranslatedPaymentMethodForHooks(string $storeId): array
    {
        return $this->getShopModule($storeId)->listTranslatedPaymentMethodForHooks('0', 0, 1000, null, []);
    }

    /**
     * @param string $storeId
     * @param int $storekeeperPaymentId
     * @return array
     * @throws \Exception
     */
    public function syncWebShopPaymentWithReturn(string $storeId, int $storekeeperPaymentId): array
    {
        return $this->getShopModule($storeId)->syncWebShopPaymentWithReturn($storekeeperPaymentId);
    }

    /**
     * @param ?int $storeKeeperPaymentMethodId
     * @param ModuleApiWrapper $shopModule
     * @param string $redirect_url
     * @param OrderInterface $order
     * @param array $payload
     * @param array $billingInfo
     * @param array $products
     * @return array
     */
    public function getStoreKeeperPayment(
        ?int $storeKeeperPaymentMethodId,
        string $redirect_url,
        OrderInterface $order,
        array $payload,
        array $billingInfo,
        array $products
    ): array {
        $shopModule = $this->getShopModule($order->getStoreId());
        if ($storeKeeperPaymentMethodId) {
            return $shopModule->newWebShopPaymentWithReturn(
                [
                    'redirect_url' => $redirect_url . '?trx={{trx}}&orderID=' . $order->getId(),
                    'amount' => $this->getOrderTotal($payload['order_items'], $shopModule),
                    'title' => 'Order: ' . $order->getIncrementId(),
                    'provider_method_id' => $storeKeeperPaymentMethodId,
                    'relation_data_id' => $payload['relation_data_id'],
                    'relation_data_snapshot' => $billingInfo,
                    'end_user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'products' => $products,
                ]
            );
        } else {
            return $shopModule->newLinkWebShopPaymentForHookWithReturn(
                [
                    'redirect_url' => $redirect_url . '?trx={{trx}}&orderID=' . $order->getId(),
                    'amount' => $this->getOrderTotal($payload['order_items'], $shopModule),
                    'title' => 'Order: ' . $order->getIncrementId(),
                    'relation_data_id' => $payload['relation_data_id'],
                    'relation_data_snapshot' => $billingInfo,
                    'end_user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'products' => $products,
                ]
            );
        }
    }

    /**
     * @param array $items
     * @param ModuleApiWrapper $shopModule
     * @return float
     */
    private function getOrderTotal(array $items, ModuleApiWrapper $shopModule): float
    {
        $order = $shopModule->newVolatileOrder(array(
            'order_items' => $items,
        ));

        return $order['value_wt'];
    }
}
