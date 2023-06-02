<?php

namespace StoreKeeper\StoreKeeper\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use StoreKeeper\StoreKeeper\Helper\Api\Auth;
use StoreKeeper\StoreKeeper\Model\Invoice;
use Magento\Framework\Controller\ResultInterface;
use StoreKeeper\StoreKeeper\Api\PaymentApiClient;

class Finish extends Action
{
    const STOREKEEPER_PAYMENT_STATUS_PAID = 'paid';
    const STOREKEEPER_PAYMENT_STATUS_CANCELLED = 'cancelled';
    const SUCCESS_PAYMENT_PATH = 'checkout/onepage/success';
    const FAIL_PAYMENT_PATH = 'checkout/cart';
    private Session $checkoutSession;
    private OrderRepository $orderRepository;
    private QuoteRepository $quoteRepository;
    private Auth $authHelper;
    private Invoice $invoice;
    private PaymentApiClient $paymentApiClient;

    /**
     * Finish constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderRepository $orderRepository
     * @param QuoteRepository $quoteRepository
     * @param Auth $authHelper
     * @param Invoice $invoice
     * @param PaymentApiClient $paymentApiClient
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderRepository $orderRepository,
        QuoteRepository $quoteRepository,
        Auth $authHelper,
        Invoice $invoice,
        PaymentApiClient $paymentApiClient
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->authHelper = $authHelper;
        $this->invoice = $invoice;
        $this->paymentApiClient = $paymentApiClient;
        parent::__construct($context);
    }

    /**
     * Finish transaction action
     *
     * @return ResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = $this->getRequest()->getParams();
        $order = $this->orderRepository->get($params['orderID']);
        $storekeeperPaymentId = $order->getStorekeeperPaymentId();
        $payment = $this->paymentApiClient->syncWebShopPaymentWithReturn($order->getStoreId(), $storekeeperPaymentId);

        if ($payment['status'] == self::STOREKEEPER_PAYMENT_STATUS_PAID) {
            $this->invoice->create($order);
            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
            $order->save();
            $this->deactivateCart();
            $resultRedirect->setPath(self::SUCCESS_PAYMENT_PATH);
        }

        if ($payment['status'] == self::STOREKEEPER_PAYMENT_STATUS_CANCELLED) {
            $cancelMessage = __('Payment canceled');
            $this->messageManager->addNoticeMessage($cancelMessage);
            $order->setState(Order::STATE_CANCELED)->setStatus(Order::STATE_CANCELED);
            $order->save();
            $resultRedirect->setPath(self::FAIL_PAYMENT_PATH);
        }

        return $resultRedirect;
    }

    /**
     * Deactivate cart
     *
     * @return void
     */
    private function deactivateCart(): void
    {
        $session = $this->checkoutSession;
        $quote = $session->getQuote();
        $quote->setIsActive(false);
        $this->quoteRepository->save($quote);
    }
}
