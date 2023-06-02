<?php

declare(strict_types = 1);

namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\ResourceModel\Order\Tax\Item as TaxItem;
use Magento\Shipping\Model\ShipmentNotifier;
use Psr\Log\LoggerInterface;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Bundle\Model\Product\Type as Bundle;
use Brick\Money\Money;
use Brick\Math\RoundingMode;
use StoreKeeper\StoreKeeper\Api\OrderApiClient;
use StoreKeeper\StoreKeeper\Api\CustomerApiClient;
use StoreKeeper\StoreKeeper\Api\PaymentApiClient;
use StoreKeeper\StoreKeeper\Api\ProductApiClient;
use StoreKeeper\StoreKeeper\Exception\EmailIsAdminUserException;

class Orders extends AbstractHelper
{
    private const BUNDLE_TYPE = 'bundle';
    private const CONFIGURABLE_TYPE = 'configurable';
    private Auth $authHelper;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private OrderRepositoryInterface $orderRepository;
    private ConvertOrder $convertOrder;
    private ShipmentNotifier $shipmentNotifier;
    private ShipmentRepositoryInterface $shipmentRepository;
    private TrackFactory $trackFactory;
    private TaxItem $taxItem;
    private LoggerInterface $logger;
    private Json $jsonSerializer;
    private Bundle $bundle;
    private $taxClassesDiscounts;
    private OrderApiClient $orderApiClient;
    private CustomerApiClient $customerApiClient;
    private PaymentApiClient $paymentApiClient;
    private ProductApiClient $productApiClient;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param ConvertOrder $convertOrder
     * @param ShipmentNotifier $shipmentNotifier
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param TrackFactory $trackFactory
     * @param TaxItem $taxItem
     * @param Context $context
     * @param LoggerInterface $logger
     * @param Json $jsonSerializer
     * @param Bundle $bundle
     * @param OrderApiClient $orderApiClient
     * @param CustomerApiClient $customerApiClient
     * @param PaymentApiClient $paymentApiClient
     * @param ProductApiClient $productApiClient
     */
    public function __construct(
        Auth $authHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        ConvertOrder $convertOrder,
        ShipmentNotifier $shipmentNotifier,
        ShipmentRepositoryInterface $shipmentRepository,
        TrackFactory $trackFactory,
        TaxItem $taxItem,
        Context $context,
        LoggerInterface $logger,
        Json $jsonSerializer,
        Bundle $bundle,
        OrderApiClient $orderApiClient,
        CustomerApiClient $customerApiClient,
        PaymentApiClient $paymentApiClient,
        ProductApiClient $productApiClient
    ) {
        parent::__construct($context);
        $this->authHelper = $authHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->convertOrder = $convertOrder;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->taxItem = $taxItem;
        $this->logger = $logger;
        $this->jsonSerializer = $jsonSerializer;
        $this->bundle = $bundle;
        $this->orderApiClient = $orderApiClient;
        $this->customerApiClient = $customerApiClient;
        $this->paymentApiClient = $paymentApiClient;
        $this->productApiClient = $productApiClient;
        $this->taxClassesDiscounts = [];
    }

    /**
     * Prepare order
     *
     * @param $order
     * @param $isUpdate
     * @return array
     */
    public function prepareOrder(Order $order, bool $isUpdate): array
    {
        /** @var $order Order */
        $email = $order->getCustomerEmail();
        $relationDataId = null;
        $orderItemsPayload = $this->prepareOrderItems($order);

        $relationDataId = $this->getRelationDataId($email, $order);

        $payload = [
            'billing_address__merge' => 'false',
            'shipping_address__merge' => 'false',
            'relation_data_id' => $relationDataId,
            'billing_address' => [
                'business_data' => [
                    'name' => $order->getBillingAddress()->getCompany(),
                    'country_iso2' => $order->getBillingAddress()->getCountryId()
                ],
                'contact_set' => [
                    'email' => $order->getCustomerEmail(),
                    'name' => $order->getBillingAddress()->getName(),
                    'phone' => $order->getBillingAddress()->getTelephone()
                ],
                'contact_person' => [
                    'firstname' => $order->getBillingAddress()->getFirstName(),
                    'familyname_prefix' => '',
                    'familyname' => $order->getBillingAddress()->getLastName(),
                ],
                'name' => $order->getBillingAddress()->getCompany() ?
                    $order->getBillingAddress()->getCompany() :
                    $order->getBillingAddress()->getName(),
                'contact_address' => $this->customerApiClient->mapAddress($order->getBillingAddress()),
                'address_billing' => $this->customerApiClient->mapAddress($order->getBillingAddress())
            ]
        ];

        if (!$order->getIsVirtual()) {
            $payload['shipping_address'] = [
                'business_data' => [
                    'name' => $order->getShippingAddress()->getCompany(),
                    'country_iso2' => $order->getShippingAddress()->getCountryId()
                ],
                'contact_set' => [
                    'email' => $order->getCustomerEmail(),
                    'name' => $order->getShippingAddress()->getName(),
                    'phone' => $order->getShippingAddress()->getTelephone()
                ],
                'contact_person' => [
                    'firstname' => $order->getShippingAddress()->getFirstName(),
                    'familyname_prefix' => '',
                    'familyname' => $order->getShippingAddress()->getLastName(),
                ],
                'name' => $order->getShippingAddress()->getCompany() ?
                    $order->getShippingAddress()->getCompany() :
                    $order->getShippingAddress()->getName(),
                'contact_address' => $this->customerApiClient->mapAddress($order->getShippingAddress())
            ];
        }

        if (!$isUpdate) {
            $payload['order_items'] = $orderItemsPayload;
            $payload['shop_order_number'] = $order->getIncrementId();
        } else {
            $payload['order_items__remove'] = null;
            $payload['order_items__do_not_change'] = true;
        }

        if ($order->getCouponCode()) {
            $payload['order_coupon_codes'] = [
                [
                    'code' => $order->getCouponCode(),
                    'value_wt' => $order->getDiscountAmount()
                ]
            ];
        }

        return $payload;
    }

    /**
     * Has refund
     *
     * @param Order $order
     * @return bool
     */
    public function hasRefund(Order $order): bool
    {
        return $order->getTotalRefunded() > 0;
    }

    /**
     * Refund all order items
     *
     * @param Order $order
     * @param string $storeKeeperId
     * @param array $refundPayments
     * @retrun void
     */
    public function refundAllOrderItems(Order $order, string $storeKeeperId, array $refundPayments): void
    {
        $this->orderApiClient->refundAllOrderItems($order->getStoreId(), $storeKeeperId, $refundPayments);
    }

    /**
     * Apply order refund
     *
     * @param Order $order
     * @retrun void
     */
    public function applyRefund(Order $order): void
    {
        $totalRefunded = $order->getTotalRefunded();

        if ((float) $totalRefunded > 0) {
            $storekeeperId = $order->getStorekeeperId();
            $storeKeeperOrder = $this->orderApiClient->getStoreKeeperOrder($order->getStoreId(), $storekeeperId);

            // check if the difference between Magento 2 and StoreKeeper exists
            $diff = ((float)$totalRefunded) - $storeKeeperOrder['paid_back_value_wt'];

            // check if the difference is a positive number
            // magento_refund - storekeeper_refund = pending_refund
            // 90             - 70                 = 20
            // in this above example we'll have to refund 20
            if ($diff > 0) {
                $storekeeperRefundId = $this->paymentApiClient->getNewWebPayment(
                    $order->getStoreId(),
                    [
                        'amount' => round(-abs($diff), 2),
                        'description' => __('Refund by Magento plugin (Order #%1)', $order->getIncrementId())
                    ]
                );

                $this->paymentApiClient->attachPaymentIdsToOrder(
                    $order->getStoreId(),
                    $storekeeperId,
                    [
                        $storekeeperRefundId
                    ]
                );
            }

            if ($totalRefunded == $order->getTotalPaid()) {
                if ($storeKeeperOrder['status'] != 'refunded') {
                    $this->refundAllOrderItems($order, $storekeeperId, [

                    ]);
                }
            }
        }
    }

    /**
     * Prepare order items
     *
     * @param Order $order
     * @return array
     */
    private function prepareOrderItems(Order $order): array
    {
        $payload = [];

        $rates = [];
        $taxFreeId = null;

        $rates = $this->productApiClient->getTaxRates($order->getStoreId(), $order->getBillingAddress()->getCountryId());
        foreach ($rates['data'] ?? [] as $rate) {
            if ($rate['alias'] == 'special_applicable_not_vat') {
                $taxFreeId = $rate['id'];
                break;
            }
        }

        if ($order->getTaxAmount() > 0) {
            $rates = $this->productApiClient->getTaxRates($order->getStoreId(), $order->getBillingAddress()->getCountryId());
        }

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() == self::BUNDLE_TYPE) {
                $bundleId = $item->getProductId();
                $payloadItems = $this->getBundlePayload($item, $taxFreeId, $rates, $order);
                foreach ($payloadItems as $payloadItem) {
                    $payload[] = $payloadItem;
                }
            } else {
                $parentIds = $this->bundle->getParentIdsByChild($item->getProductId());
                if ($item->getParentItemId() || (isset($bundleId) && in_array($bundleId, $parentIds))) {
                    continue;
                }
                $payload[] = $this->getSimpleProductPayload($item, $taxFreeId, $rates);
            }
        }

        if (!$order->getIsVirtual()) {
            $payloadItem = [
                'sku' => $order->getShippingMethod(),
                'ppu_wt' => $order->getShippingAmount() + $order->getShippingTaxAmount(),
                'quantity' => 1,
                'name' => $order->getShippingDescription(),
                'is_shipping' => true,
            ];

            if ($order->getShippingTaxAmount() > 0) {
                $tax_items = $this->taxItem->getTaxItemsByOrderId($order->getId());

                if (!empty($tax_items)) {
                    foreach ($tax_items as $tax_item) {
                        if ($tax_item['taxable_item_type'] == 'shipping') {
                            $taxPercent = ((float) $tax_item['tax_percent']) / 100;
                            if ($taxPercent > 0) {
                                $rateId = null;
                                foreach ($rates['data'] as $rate) {
                                    if ($rate['value'] == $taxPercent) {
                                        $rateId = $rate['id'];
                                    }
                                }
                                $payloadItem['tax_rate_id'] = $rateId;
                            }
                            break;
                        }
                    }
                }
            } elseif (!empty($taxFreeId)) {
                $payloadItem['tax_rate_id'] = $taxFreeId;
            }

            $payload[] = $payloadItem;
        }

        if ($this->taxClassesDiscounts) {
            foreach ($this->taxClassesDiscounts as $taxPercent => $taxClassDiscount) {
                $payload[] = $this->getDiscountPayload($rates, $order, $taxFreeId, $taxPercent, $taxClassDiscount);
            }
        }

        return $payload;
    }

    /**
     * Get StoreKeeper Order
     *
     * @param string $storeId
     * @param string $storeKeeperId
     * @retrun ?array
     */
    public function getStoreKeeperOrder(string $storeId, string $storeKeeperId): ?array
    {
        try {
            $response = $this->orderApiClient->getStoreKeeperOrder($storeId, $storeKeeperId);
            if (is_array($response)) {
                return $response;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    /**
     * Update order by id
     *
     * @param string $storeId
     * @param string $storeKeeperId
     * @throws LocalizedException
     * @retrun void
     */
    public function updateById(string $storeId, string $storeKeeperId): void
    {
        $storeKeeperOrder = $this->getStoreKeeperOrder($storeId, $storeKeeperId);

        if ($storeKeeperOrder === null) {
            return;
        }

        /** @var Order $order */
        $order = $this->getOrderByStoreKeeperId($storeKeeperId);

        if ($order && $order->getStatus() !== 'canceled') {
            $this->createShipment($order, $storeKeeperId);
        }
    }

    /**
     * Create shipment
     *
     * @param Order $order
     * @param string $storeKeeperId
     * @throws LocalizedException
     * @retrun void
     */
    public function createShipment(Order $order, string $storeKeeperId): void
    {
        $storeKeeperOrder = $this->getStoreKeeperOrder($order->getStoreId(), $storeKeeperId);

        if (isset($storeKeeperOrder['shipped_item_no']) && !$order->hasShipments()) {
            $shippedItem = (int) $storeKeeperOrder['shipped_item_no'];

            if ($shippedItem > 0) {
                if (!$order->canShip()) {
                    throw new LocalizedException(
                        __('You can\'t create an shipment.')
                    );
                }

                $shipment = $this->convertOrder->toShipment($order);

                foreach ($order->getAllItems() as $orderItem) {
                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                        continue;
                    }

                    $qtyShipped = $orderItem->getQtyToShip();
                    $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                    $shipment->addItem($shipmentItem);
                }

                $track = $this->trackFactory->create();
                $statusUrl = $statusUrl = $this->orderApiClient->getOrderStatusPageUrl($order->getStoreId(), $storeKeeperId);
                $track->setNumber($storeKeeperId);
                $track->setCarrierCode($storeKeeperOrder['shipping_name']);
                $track->setTitle('StoreKeeper Shipment Tracking Number');
                $track->setDescription('Shipping ');
                $track->setUrl($statusUrl);

                $shipment->addTrack($track);

                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);

                try {
                    $shipment->getExtensionAttributes()->setIsStorekeeper(true);
                    $this->shipmentRepository->save($shipment);
                    $this->orderRepository->save($shipment->getOrder());
                    $this->shipmentNotifier->notify($shipment);
                } catch (\Exception $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __($e->getMessage())
                    );
                }
            }
        }
    }

    /**
     * Get order by StoreKeeperId
     *
     * @param string $storeKeeperId
     * @return ?Order
     */
    public function getOrderByStoreKeeperId(string $storeKeeperId): ?Order
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('storekeeper_id', $storeKeeperId, 'eq')->create();

        return current($this->orderRepository->getList($searchCriteria)->getItems());
    }

    /**
     * Get result StoreKeeperId
     *
     * @param array $result
     * @return mixed
     */
    private function getResultStoreKeeperId(array $result): mixed
    {
        return $result['order_id'];
    }

    /**
     * If SK order exist
     *
     * @param Order $order
     * @retrun ?string
     */
    public function exists(Order $order): ?string
    {
        $storeKeeperId = $order->getStorekeeperId();

        if ($storeKeeperId > 0) {
            return $storeKeeperId;
        }

        return null;
    }

    /**
     * Update order
     *
     * @param Order $order
     * @param string $storeKeeperId
     * @throws LocalizedException
     * @retrun void
     */
    public function update(Order $order, string $storeKeeperId): void
    {
        $storeKeeperOrder = $this->getStoreKeeperOrder($order->getStoreId(), $storeKeeperId);

        // it might be so that this store has been previously connected and has "old"
        // StoreKeeper orders
        if (empty($storeKeeperOrder)) {
            return;
        }

        $statusMapping = $this->statusMapping();
        if (!isset($storeKeeperOrder['status'])) {
            // no status
            return;
        }

        if ($order->getStatus() != 'closed' && $storeKeeperOrder['status'] != 'canceled') {
            if (isset($statusMapping[$storeKeeperOrder['status']])) {
                if ($statusMapping[$storeKeeperOrder['status']]
                    !== $order->getStatus() && $storeKeeperOrder['status']
                    !== 'complete') {
                    $this->updateStoreKeeperOrderStatus($order, $storeKeeperId);
                }
            }

            $this->updateStoreKeeperOrder($order, $storeKeeperId);
            $this->createShipment($order, $storeKeeperId);
        }

        if ($this->hasRefund($order)) {
            $this->applyRefund($order);
        }

        $order->setStorekeeperOrderLastSync(time());
        $order->setStorekeeperOrderPendingSync(0);
        $order->setStorekeeperOrderPendingSyncSkip(true);
        $order->setStorekeeperOrderNumber($storeKeeperOrder['number']);

        try {
            $this->orderRepository->save($order);
        } catch (GeneralException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * get order status Mapping
     *
     * @return array
     */
    private function statusMapping(): array
    {
        return [
            'complete' => 'complete',
            'processing' => 'processing',
            'cancelled' => 'canceled',
            'new' => 'new'
        ];
    }

    /**
     * Update StoreKeeper order status
     *
     * @param Order $order
     * @param string $storeKeeperId
     * @throws LocalizedException
     * @retrun void
     */
    public function updateStoreKeeperOrderStatus(Order $order, string $storeKeeperId): void
    {
        $statusMapping = $this->statusMapping();

        try {
            if ($status = array_search($order->getStatus(), $statusMapping)) {
                $this->orderApiClient->updateOrderStatus($order->getStoreId(), ['status' => $status], $storeKeeperId);
            }
        } catch (GeneralException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * Update StoreKeeper order
     *
     * @param Order $order
     * @param string $storeKeeperId
     * @throws LocalizedException
     * @retrun void
     */
    public function updateStoreKeeperOrder(Order $order, string $storeKeeperId): void
    {
        $payload = $this->prepareOrder($order, true);

        try {
            $this->orderApiClient->updateOrder($order->getStoreId(), $payload, $storeKeeperId);
        } catch (GeneralException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }

    /**
     * On SK order create
     *
     * @param Order $order
     * @throws LocalizedException
     * @retrun void
     */
    public function onCreate(Order $order): void
    {
        $storeId = $order->getStoreid();
        $payload = $this->prepareOrder($order, false);
        $this->logger->info(
            'Order #' . $order->getId() . ' payload: ' . $this->jsonSerializer->serialize($payload)
        );
        $storeKeeperOrder = $this->orderApiClient->getNewOrderWithReturn($storeId, $payload);
        $storeKeeperId = $storeKeeperOrder['id'];
        $order->setStorekeeperId($storeKeeperId);
        $order->setStorekeeperOrderLastSync(time());
        $order->setStorekeeperOrderPendingSync(0);
        $order->setStorekeeperOrderPendingSyncSkip(true);
        $order->setStorekeeperOrderNumber($storeKeeperOrder['number']);

        try {
            $this->orderRepository->save($order);
        } catch (GeneralException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }

        if ($order->getPaymentsCollection()->count() && $order->getStatus() !== 'canceled') {
            $paymentId = $order->getStorekeeperPaymentId();

            if ($paymentId) {
                try {
                    $this->paymentApiClient->attachPaymentIdsToOrder($storeId, $storeKeeperId, [$paymentId]);
                } catch (GeneralException $e) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __($e->getMessage())
                    );
                }
            }
        }

        if ($this->hasRefund($order)) {
            $this->applyRefund($order);
        }
    }

    /**
     * Get orders
     *
     * @param string $storeId
     * @param int $page
     * @param int $pageSize
     * @return OrderSearchResultInterface
     */
    public function getOrders(string $storeId, int $page, int $pageSize): OrderSearchResultInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(
                'store_id',
                $storeId,
                'eq'
            )
            ->addFilter(
                'storekeeper_order_pending_sync',
                1,
                'eq'
            )
            ->setPageSize(
                $pageSize
            )
            ->setCurrentPage($page)
            ->create();

        return $this->orderRepository->getList($searchCriteria);
    }

    /**
     * Get Order Item price
     *
     * @param Item $item
     * @return float
     */
    private function getItemPrice(Item $item): float
    {
        $itemPrice = 0.0;
        $parentProduct = $item->getParentItem();
        if ($parentProduct) {
            $parentProductType = $parentProduct->getProductType();
            if ($parentProductType == self::BUNDLE_TYPE) {
                $itemPrice = $item->getPrice();
            } elseif ($parentProductType == self::CONFIGURABLE_TYPE) {
                $itemPrice = $parentProduct->getPrice();
            }
        } else {
            $itemPrice = $item->getPrice();
        }

        return $this->getPriceValueForPayload($itemPrice, $item->getOrder());
    }

    /**
     * Get Bundle order item product payload
     *
     * @param Item $item
     * @param int|null $taxFreeId
     * @param array $rates
     * @param Order $order
     * @return array
     */
    private function getBundlePayload(Item $item, ?int $taxFreeId, array $rates, Order $order): array
    {
        // total of bundle's items prices as simple products
        $bundleItemsPriceTotal = null;

        // total of bundle's items prices as option products
        $bundleOptionItemsTotal = null;

        $bundlePrice = $this->getBrickMoneyPrice($item->getPrice(), $order);
        $bundlePriceValue = $this->getPriceByBrickMoneyObj($bundlePrice);

        $parentProduct = $this->getParentProductData($item);

        foreach ($item->getChildrenItems() as $bundleItem) {
            $this->logItemTaxAmount($bundleItem, $order);
            if ($item->getDiscountAmount() != 0) {
                $this->calculateTaxClassesDiscounts($bundleItem, $order);
            }
            $bundleItemSku = $bundleItem->getSku();
            $bundleItemPrice = $this->getBrickMoneyPrice($bundleItem->getProduct()->getPrice(), $order);

            if ($bundleItemsPriceTotal == null) {
                $bundleItemsPriceTotal = $bundleItemPrice;
            } else {
                $bundleItemsPriceTotal = $bundleItemPrice->plus($bundleItemsPriceTotal);
            }

            $bundleItemData = $this->jsonSerializer->unserialize(
                $bundleItem->getProductOptions()['bundle_selection_attributes']
            );
            $bundleOptionItemPrice = $this->getBrickMoneyPrice($bundleItemData['price'], $order);
            $bundleOptionItemsTotal = $bundleOptionItemsTotal
                ? $bundleOptionItemPrice->plus($bundleOptionItemsTotal)
                : $bundleOptionItemPrice;
            $bundleOptionItemsTotalValue = $this->getPriceByBrickMoneyObj($bundleOptionItemsTotal);

            $hasDiscount = $bundleItem->getDiscountPercent() != 0;
            $bundleItemWithDiscountData = $hasDiscount ? $this->getBundleItemWithDiscountData($bundleItem) : null;

            $bundlePayloadItem = [
                'quantity' => $bundleItem->getQtyOrdered(),
                'sku' => $bundleItemSku,
                'name' => $bundleItem->getName(),
                'description' => $bundleItemData['option_label'],
                'tax_rate_id' => $this->getPriceByBrickMoneyObj($bundleOptionItemPrice)
                    ? $this->getTaxRateId($bundleItem, $taxFreeId, $rates)
                    : $this->getTaxRateId($item, $taxFreeId, $rates),
                'extra' => [
                    'external_id' => $bundleItem->getProduct()->getId(),
                    'options' => [
                        'option' => $bundleItemData['option_label']
                    ],
                    'parent_product' => $parentProduct
                ]
            ];
            foreach ($this->getPricePerUnitPayload($item, $bundleItem, $order, $hasDiscount, $bundleItemWithDiscountData) as $key => $value) {
                $bundlePayloadItem[$key] = $value;
            }

            $bundlePayload[] = $bundlePayloadItem;
        }

        $bundleDiscount = $bundlePrice->minus($bundleItemsPriceTotal);
        $bundleDiscountValue = $this->getPriceByBrickMoneyObj($bundleDiscount);

        if ($item->getTaxPercent() && $bundleDiscountValue != 0 && $bundleOptionItemsTotalValue != 0) {
            $bundlePayload[] = $this->getBundleDiscountData(
                $item,
                $taxFreeId,
                $rates,
                $bundleDiscountValue,
                $parentProduct
            );
        }

        if ($bundleOptionItemsTotalValue == 0 && $bundlePriceValue > $bundleOptionItemsTotalValue) {
            $bundlePayload[] = $this->getBundleCompensateData(
                $bundlePriceValue,
                $parentProduct,
                $item,
                $taxFreeId,
                $rates
            );
        }

        return $bundlePayload;
    }

    /**
     * Get simple order item product payload
     *
     * @param Item $item
     * @param int|null $taxFreeId
     * @param array $rates
     * @return array
     */
    private function getSimpleProductPayload(Item $item, ?int $taxFreeId, array $rates): array
    {
        $order = $item->getOrder();
        $this->logItemTaxAmount($item, $order);
        if ($item->getDiscountAmount() != 0) {
            $this->calculateTaxClassesDiscounts($item, $order);
        }
        $isConfigurableProduct = $item->getProductType() == self::CONFIGURABLE_TYPE;

        if ($isConfigurableProduct) {
            $payloadItem = $this->getConfigurableProductData($item);
        } else {
            $shopProductId = '';

            if ($item->getProduct() !== null && $item->getProduct()->getStorekeeperProductId()) {
                $shopProductId = $item->getProduct()->getStorekeeperProductId();
            }

            $payloadItem = [
                'sku' => $item->getSku(),
                'quantity' => $item->getQtyOrdered(),
                'name' => $item->getName(),
                'shop_product_id' => $shopProductId,
            ];
        }
        if (((float)$item->getTaxAmount()) > 0) {
            $payloadItem['ppu_wt'] = $this->getPriceValueForPayload($item->getPriceInclTax(), $order);
            $payloadItem['before_discount_ppu_wt'] = $this->getPriceValueForPayload($item->getOriginalPrice(), $order);
        } else {
            $itemPrice = $this->getItemPrice($item);
            $itemOriginalPrice = $this->getPriceValueForPayload($item->getOriginalPrice(), $order);
            $payloadItem['ppu_wt'] = $itemPrice;
            if ($itemPrice != $itemOriginalPrice) {
                $payloadItem['before_discount_ppu_wt'] = $itemOriginalPrice;
            }
        }

        $payloadItem['tax_rate_id'] = $this->getTaxRateId($item, $taxFreeId, $rates);

        return $payloadItem;
    }

    /**
     * Get parent product data
     *
     * @param Item $item
     * @return array
     */
    private function getParentProductData(Item $item): array
    {
        return [
            'external_id' => $item->getProductId(),
            'sku' => $item->getProduct()->getSku(),
            'name' => $item->getProduct()->getName()
        ];
    }

    /**
     * Get bundle order item discount data
     *
     * @param Item $item
     * @param int|null $taxFreeId
     * @param array $rates
     * @param float $bundleDiscount
     * @param array $parentProduct
     * @return array
     */
    private function getBundleDiscountData(
        Item $item,
        ?int $taxFreeId,
        array $rates,
        float $bundleDiscount,
        array
        $parentProduct
    ): array
    {
        return [
            'quantity' => 1,
            'ppu_wt' => $bundleDiscount,
            'sku' => $parentProduct['sku'],
            'is_discount' => true,
            'name' => $parentProduct['name'],
            'tax_rate_id' => $this->getTaxRateId($item, $taxFreeId, $rates)
        ];
    }

    /**
     * Get bundle item with discount data
     *
     * @param Item $bundleItem
     * @return array
     */
    private function getBundleItemWithDiscountData(Item $bundleItem): array
    {
        $bundleItemPrice = $this->getBrickMoneyPrice($bundleItem->getPrice(), $bundleItem->getOrder());
        $bundleItemDiscount = $this->getBrickMoneyPrice($bundleItem->getDiscountAmount(), $bundleItem->getOrder());
        $bundleItemPriceWithoutDiscount = $bundleItemPrice->minus($bundleItemDiscount);
        return [
            'before_discount_ppu_wt' => $this->getPriceValueForPayload(
                $bundleItem->getPrice(),
                $bundleItem->getOrder()
            ),
            'ppu_wt' => $this->getPriceByBrickMoneyObj($bundleItemPriceWithoutDiscount)
        ];
    }

    /**
     * Get bundle compensate data
     *
     * @param float $bundlePrice
     * @param array $parentProduct
     * @param Item $item
     * @param int|null $taxFreeId
     * @param array $rates
     * @return array
     */
    private function getBundleCompensateData(
        float $bundlePrice,
        array $parentProduct,
        Item $item,
        ?int $taxFreeId,
        array $rates
    ): array
    {
        return [
            'quantity' => 1,
            'ppu_wt' => $bundlePrice,
            'sku' => $parentProduct['sku'],
            'name' => $parentProduct['name'],
            'description' => $parentProduct['name'],
            'tax_rate_id' => $this->getTaxRateId($item, $taxFreeId, $rates),
            'extra' => [
                'external_id' => $parentProduct['external_id'],
                'parent_product' => $parentProduct
            ]
        ];
    }

    /**
     * Get configurable product data
     *
     * @param Item $item
     * @return array
     */
    private function getConfigurableProductData(Item $item): array
    {
        foreach ($item->getProductOptions()['attributes_info'] as $attribute) {
            $productConfigurableAttributes[$attribute['label']] = $attribute['value'];
        }
        foreach ($item->getChildrenItems() as $configurableProductOption) {
            $configurableProductData = [
                'sku' => $configurableProductOption->getSku(),
                'quantity' => $item->getQtyOrdered(),
                'name' => $configurableProductOption->getName(),
                'description' => $configurableProductOption->getDescription(),
                'extra' => [
                    'external_id' => $configurableProductOption->getProductId(),
                    'options' => $productConfigurableAttributes,
                    'parent_product' => $this->getParentProductData($item)
                ]
            ];
        }

        return $configurableProductData;
    }

    /**
     * Get TaxRate Id
     *
     * @param Item $item
     * @param int|null $taxFreeId
     * @param array $rates
     * @return ?int
     */
    private function getTaxRateId(Item $item, ?int $taxFreeId, array $rates): ?int
    {
        $taxPercent = ((float) $item->getTaxPercent()) / 100;
        if ($taxPercent > 0) {
            $rateId = null;
            foreach ($rates['data'] ?? [] as $rate) {
                if ($rate['value'] == $taxPercent) {
                    $rateId = $rate['id'];
                    break;
                }
            }
            $taxRateId = $rateId;
        } elseif (!empty($taxFreeId)) {
            $taxRateId = $taxFreeId;
        } else {
            throw new \Exception('Item with SKU: "' . $item->getSku() . '" doesn\'t match any tax rate');
        }

        return $taxRateId;
    }

    /**
     * Get BrickMoney Price
     *
     * @param int|float|string $price
     * @param Order $order
     * @return Money
     */
    private function getBrickMoneyPrice($price, Order $order): Money
    {
        return Money::of($price, $order->getStoreCurrencyCode());
    }

    /**
     * Get PriceValue for payload
     *
     * @param int|float|string $price
     * @return float
     */
    private function getPriceValueForPayload($price, Order $order): float
    {
        return $this->getPriceByBrickMoneyObj($this->getBrickMoneyPrice($price, $order));
    }

    /**
     * Get price by BrickMoney object
     *
     * @param Money $brickMoneyObj
     * @return float
     */
    private function getPriceByBrickMoneyObj(Money $brickMoneyObj): float
    {
        return $brickMoneyObj->to(
            $brickMoneyObj->getContext(),
            RoundingMode::HALF_UP
        )->getAmount()->toFloat();
    }

    /**
     * Get discount payload
     *
     * @param array $rates
     * @param Order $order
     * @param int|null $taxFreeId
     * @param string $taxPercent
     * @param Money $taxClassDiscount
     * @return array
     */
    private function getDiscountPayload(
        array $rates,
        Order $order,
        ?int $taxFreeId,
        string $taxPercent,
        Money $taxClassDiscount
    ): array
    {
        foreach ($rates['data'] as $rate) {
            if ($rate['value'] == $taxPercent / 100) {
                return [
                    'is_discount' => true,
                    'name' => $order->getDiscountDescription(),
                    'sku' => $order->getCouponCode(),
                    'ppu_wt' => -$this->getPriceByBrickMoneyObj($taxClassDiscount),
                    'quantity' => 1,
                    'tax_rate_id' => $rate['id']
                ];
            }
        }
    }

    /**
     * Calculate Tax class discounts
     *
     * @param Item $item
     * @param Order $order
     * @return void
     */
    private function calculateTaxClassesDiscounts(Item $item, Order $order): void
    {
        $itemTaxPercent = $item->getTaxPercent();
        $itemDiscountAmount = $item->getDiscountAmount();
        if (!key_exists($itemTaxPercent, $this->taxClassesDiscounts)) {
            $this->taxClassesDiscounts[$itemTaxPercent] = $this->getBrickMoneyPrice($itemDiscountAmount, $order);
        } else {
            foreach ($this->taxClassesDiscounts as $key => $value) {
                if ($key == $itemTaxPercent) {
                    $this->taxClassesDiscounts[$key] = $value->plus(
                        $this->getBrickMoneyPrice($itemDiscountAmount,
                            $order)
                    );
                }
            }
        }
    }

    /**
     * Log order i tem Tax Amount
     *
     * @param Item $item
     * @param Order $order
     * @return void
     */
    private function logItemTaxAmount(Item $item, Order $order): void
    {
        $itemTaxAmount = $this->getPriceValueForPayload($item->getTaxAmount(), $order);
        $this->logger->info(
            'Order:#' . $order->getId() . ', item: SKU:"' . $item->getSku() . '", tax amount:' . $itemTaxAmount
        );
    }

    /**
     * Get price per unit payload
     *
     * @param Item $item
     * @param Item $bundleItem
     * @param Order $order
     * @param bool $hasDiscount
     * @param array|null $bundleItemWithDiscountData
     * @return array
     */
    private function getPricePerUnitPayload(
        Item $item,
        Item $bundleItem,
        Order $order,
        bool $hasDiscount,
        ?array $bundleItemWithDiscountData
    ): array
    {
        $bundleItemOriginalPrice = $bundleItem->getOriginalPrice();
        $bundleItemSpecialPrice = $bundleItem->getPrice();
        if (!$item->getTaxPercent() && $bundleItemOriginalPrice != $bundleItemSpecialPrice) {
            $payload = [
                'before_discount_ppu' => $this->getPriceValueForPayload($bundleItemOriginalPrice, $order),
                'ppu' => $this->getPriceValueForPayload($bundleItemSpecialPrice, $order)
            ];
        } else {
            $priceWithTax = $bundleItem->getPriceInclTax();
            $bundleItemPriceWithTax = $priceWithTax ? $this->getPriceValueForPayload($bundleItem->getPriceInclTax(), $order) : 0.0;
            $payload = [
                'before_discount_ppu_wt' => $hasDiscount ? $bundleItemWithDiscountData['before_discount_ppu_wt'] : $bundleItemPriceWithTax,
                'ppu_wt' => $hasDiscount ? $bundleItemWithDiscountData['ppu_wt'] : $bundleItemPriceWithTax
            ];

        }

        return $payload;
    }

    /**
     * @param string $email
     * @param Order $order
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRelationDataId(string $email, Order $order): int
    {
        $storeId = $order->getStoreId();
        try {
            $relationDataId = $this->findCustomerRelationDataIdByEmail($email, $storeId);
        } catch (EmailIsAdminUserException $e) {
            $storeBaseUrl = parse_url($this->authHelper->getStoreBaseUrl())['host'];
            if (!$order->getCustomerIsGuest()) {
                $email = 'nomail+' . $order->getCustomerId() . '@' . $storeBaseUrl;
            } else {
                $email = 'nomail+' . crc32($email) . '@' . $storeBaseUrl;
            }

        }
        if( empty($relationDataId)){
            $relationDataId = $this->customerApiClient->createStorekeeperCustomerByOrder($email, $order);
        }

        return $relationDataId;
    }

    /**
     * Find customer relation dataId by email
     *
     * @param string $email
     * @param string $storeId
     * @return false|int
     */
    protected function findCustomerRelationDataIdByEmail(string $email, string $storeId): ?int
    {
        $id = null;
        if (!empty($email)) {
            try {
                $customer = $this->customerApiClient->findShopCustomerBySubuserEmail($storeId, $email);
                $id = (int)$customer['id'];
            } catch (GeneralException $exception) {
                if( $exception->getApiExceptionClass() == 'ShopModule::EmailIsAdminUser' ){
                    throw new EmailIsAdminUserException($exception->getMessage(), 0, $exception);
                }
                throw $exception;
            }
        }

        return $id;
    }
}
