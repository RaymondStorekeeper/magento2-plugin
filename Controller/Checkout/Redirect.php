<?php

namespace StoreKeeper\StoreKeeper\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\OrderRepository;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\StoreKeeper\Model\OrderItems;
use StoreKeeper\StoreKeeper\Model\CustomerInfo;
use StoreKeeper\StoreKeeper\Helper\Api\Auth;
use StoreKeeper\StoreKeeper\Helper\Api\Orders as OrdersHelper;
use StoreKeeper\ApiWrapper\ModuleApiWrapper;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;

class Redirect extends Action
{
    private const FINISH_PAGE_ROUTE = 'storekeeper_payment/checkout/finish';

    private Session $checkoutSession;

    private QuoteRepository $quoteRepository;

    private OrderRepository $orderRepository;

    private Auth $authHelper;

    private OrdersHelper $ordersHelper;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param QuoteRepository $quoteRepository
     * @param OrderRepository $orderRepository
     * @param Auth $authHelper
     * @param OrdersHelper $ordersHelper
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        QuoteRepository $quoteRepository,
        OrderRepository $orderRepository,
        Auth $authHelper,
        OrdersHelper $ordersHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->authHelper = $authHelper;
        $this->ordersHelper = $ordersHelper;
        parent::__construct($context);
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $payload = $this->ordersHelper->prepareOrder($order, false);
            $redirect_url =  $this->_url->getUrl(self::FINISH_PAGE_ROUTE);
            $shopModule = $this->authHelper->getModule('ShopModule', $order->getStoreid());
            $products = $this->getPaymentProductFromOrderItems($payload['order_items']);
            $billingInfo = $this->applyAddressName($payload['billing_address'] ?? $payload['shipping_address']);

            $payment = $shopModule->newLinkWebShopPaymentForHookWithReturn(
                [
                    'redirect_url' => $redirect_url . '?trx={{trx}}&orderId=' . $order->getId(),
                    'amount' => $this->getOrderTotal($payload['order_items'], $shopModule),
                    'title' => 'Order: ' . $order->getIncrementId(),
                    'relation_data_id' => $payload['relation_data_id'],
                    'relation_data_snapshot' => $billingInfo,
                    'end_user_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'products' => $products,
                ]
            );

            $order->setStorekeeperPaymentId($payment['id']);

            try {
                $this->orderRepository->save($order);
            } catch (GeneralException $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }

            if (empty($order)) {
                throw new Error('No order found in session, please try again');
            }

            # Restore the quote
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $quote->setIsActive(true)->setReservedOrderId(null);
            $this->checkoutSession->replaceQuote($quote);
            $this->quoteRepository->save($quote);

            $this->getResponse()->setNoCacheHeaders();
            $this->getResponse()->setRedirect($payment['payment_url']);

        } catch (\Exception $e) {
            $this->_getCheckoutSession()->restoreQuote();
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            payHelper::logCritical($e, array(), $order->getStore());

            $this->_redirect('checkout/cart');
        }
    }

    /**
     * @return Session
     */
    protected function _getCheckoutSession(): Session
    {
        return $this->checkoutSession;
    }

    /**
     * @param array $items
     * @return array
     */
    protected function getPaymentProductFromOrderItems(array $items): array
    {
        $products = [];
        foreach ($items as $orderProduct) {
            $products[] = array_intersect_key(
                $orderProduct,
                array_flip(['sku', 'name', 'ppu_wt', 'quantity', 'is_shipping', 'is_payment', 'is_discount'])
            );
        }
        return $products;
    }

    /**
     * @param array $customerInfo
     * @return array
     */
    protected function applyAddressName(array $customerInfo): array
    {
        $personName = implode(
            ' ',
            array_filter(
                [
                    $customerInfo['contact_person']['firstname'] ?? null,
                    $customerInfo['contact_person']['familyname_prefix'] ?? null,
                    $customerInfo['contact_person']['familyname'] ?? null,
                ]
            )
        );
        $customerInfo['name'] = $customerInfo['name'] ?? $customerInfo['business_data']['name'] ?? $personName;
        $customerInfo['contact_address']['name'] = $customerInfo['contact_address']['name']??  $personName;

        return $customerInfo;
    }

    /**
     * @param array $items
     * @param ModuleApiWrapper $shopModule
     * @return float
     */
    function getOrderTotal(array $items, ModuleApiWrapper $shopModule): float
    {
        $order = $shopModule->newVolatileOrder(array(
            'order_items' => $items,
        ));

        return $order['value_wt'];
    }
}
