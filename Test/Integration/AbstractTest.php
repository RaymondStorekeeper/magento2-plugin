<?php

namespace StoreKeeper\StoreKeeper\Test\Integration;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Customer;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Magento\Tax\Model\ClassModel as TaxClassModel;
use Magento\Tax\Model\Calculation\Rate as TaxRateCalculation;
use Magento\Tax\Model\Calculation\Rule as TaxRuleCalculation;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Quote\Model\GuestCart\GuestCartManagement;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;

class AbstractTest extends TestCase
{
    const ORDER_INCREMENT_ID = '100001007';
    const PRODUCT_SKU = 'simple-2';
    const STORE_KEEPER_ORDER_NUMBER = 'S08-' . self::ORDER_INCREMENT_ID;
    const STORE_KEEPER_ORDER_ID = 55;

    protected $customerApiClientMock;
    protected $productApiClientMock;
    protected $orderApiClientMock;
    protected $requestMock;
    protected $paymentApiClientMock;

    protected $searchCriteriaBuilder;
    protected $orderRepository;
    protected $customerSession;
    protected $cartManagement;
    protected $cartItemRepository;
    protected $shippingAddressManagement;
    protected $totalsInformationManagement;
    protected $paymentMethodManagement;
    protected $cartRepository;
    protected $checkoutSession;
    protected $persistentSessionHelper;
    protected $url;
    protected $quoteRepository;
    protected $response;
    protected $resultRedirectFactory;
    protected $invoice;
    protected $apiOrders;
    protected $cronOrders;
    protected $redirect;
    protected $finish;
    protected $guestCartManagement;
    protected $authHelper;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->customerApiClientMock = $this->createMock(\StoreKeeper\StoreKeeper\Api\CustomerApiClient::class);
        $this->productApiClientMock = $this->createMock(\StoreKeeper\StoreKeeper\Api\ProductApiClient::class);
        $this->orderApiClientMock = $this->createMock(\StoreKeeper\StoreKeeper\Api\OrderApiClient::class);
        $this->requestMock = $this->createMock(\Magento\TestFramework\Request::class);
        $this->paymentApiClientMock = $this->createMock(\StoreKeeper\StoreKeeper\Api\PaymentApiClient::class);

        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $this->orderRepository = Bootstrap::getObjectManager()->create(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->customerSession = Bootstrap::getObjectManager()->create(\Magento\Customer\Model\Session::class);
        $this->cartManagement = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartManagementInterface::class);
        $this->cartItemRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartItemRepositoryInterface::class);
        $this->shippingAddressManagement = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\ShippingAddressManagementInterface::class);
        $this->totalsInformationManagement = Bootstrap::getObjectManager()->create(\Magento\Checkout\Api\TotalsInformationManagementInterface::class);
        $this->paymentMethodManagement = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\PaymentMethodManagementInterface::class);
        $this->cartRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Api\CartRepositoryInterface::class);
        $this->checkoutSession = Bootstrap::getObjectManager()->create(\Magento\Checkout\Model\Session::class);
        $this->persistentSessionHelper = Bootstrap::getObjectManager()->create(\Magento\Persistent\Helper\Session::class);
        $this->url = Bootstrap::getObjectManager()->create(\Magento\Framework\UrlInterface::class);
        $this->quoteRepository = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\QuoteRepository::class);
        $this->response = Bootstrap::getObjectManager()->create(\Magento\Framework\App\ResponseInterface::class);
        $this->resultRedirectFactory = Bootstrap::getObjectManager()->create(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $this->invoice = Bootstrap::getObjectManager()->create(\StoreKeeper\StoreKeeper\Model\Invoice::class);
        $this->guestCartManagement = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\GuestCart\GuestCartManagement::class);
        $this->maskedQuoteIdToQuoteId = Bootstrap::getObjectManager()->create(\Magento\Quote\Model\MaskedQuoteIdToQuoteId::class);
        $this->authHelper = Bootstrap::getObjectManager()->create(\StoreKeeper\StoreKeeper\Helper\Api\Auth::class);

        $this->customerApiClientMock->method('findCustomerRelationDataIdByEmail')
            ->willReturn(69);
        $this->customerApiClientMock->method('createStorekeeperCustomerByOrder')
            ->willReturn(99);
        $this->productApiClientMock->method('getTaxRates')
            ->willReturn($this->getTaxRates());
        $this->orderApiClientMock->method('getNewOrderWithReturn')
            ->willReturn($this->getStoreKeeperOrder());
        $this->orderApiClientMock->method('getStoreKeeperOrder')
            ->willReturn(
                [
                    'paid_back_value_wt' => 50
                ]
            );
        $this->requestMock->method('getParam')
            ->willReturnCallback(
                function ($key) {
                    if ($key == 'storekeeper_payment_method_id') {
                        return '1';
                    }
                }
            );
        $this->requestMock->method('getParams')
            ->willReturn([
                'orderID' => '1'
            ]);
        $this->paymentApiClientMock->method('getStoreKeeperPayment')
            ->willReturn([
                'id' => 1,
                'payment_url' => 'https://storekeepercloud.com/'
            ]);
        $this->paymentApiClientMock->method('syncWebShopPaymentWithReturn')
            ->willReturn([
                'status' => 'paid'
            ]);
        $this->paymentApiClientMock->method('getNewWebPayment')
            ->willReturn(33);

        $this->apiOrders = $objectManager->getObject(
            \StoreKeeper\StoreKeeper\Helper\Api\Orders::class,
            [
                'customerApiClient' => $this->customerApiClientMock,
                'productApiClient' => $this->productApiClientMock,
                'orderApiClient' => $this->orderApiClientMock,
                'searchCriteriaBuilder' => $this->searchCriteriaBuilder,
                'orderRepository' => $this->orderRepository,
                'paymentApiClient' => $this->paymentApiClientMock,
                'authHelper' => $this->authHelper
            ]
        );
        $this->cronOrders = $objectManager->getObject(
            \StoreKeeper\StoreKeeper\Cron\Orders::class,
            [
                'storeManager' => Bootstrap::getObjectManager()->create(\Magento\TestFramework\Store\StoreManager::class),
                'configHelper' => Bootstrap::getObjectManager()->create(\StoreKeeper\StoreKeeper\Helper\Config::class),
                'ordersHelper' => $this->apiOrders,
                'storeKeeperFailedSyncOrder' => Bootstrap::getObjectManager()->create(\StoreKeeper\StoreKeeper\Model\StoreKeeperFailedSyncOrderFactory::class)
            ]
        );
        $this->redirect = $objectManager->getObject(
            \StoreKeeper\StoreKeeper\Controller\Checkout\Redirect::class,
            [
                'request' => $this->requestMock,
                'checkoutSession' => $this->checkoutSession,
                'ordersHelper' => $this->apiOrders,
                '_url' => $this->url,
                'paymentApiClient' => $this->paymentApiClientMock,
                'quoteRepository' => $this->quoteRepository,
                'orderRepository' => $this->orderRepository,
                '_response' => $this->response
            ]
        );
        $this->finish = $objectManager->getObject(
            \StoreKeeper\StoreKeeper\Controller\Checkout\Finish::class,
            [
                'resultRedirectFactory' => $this->resultRedirectFactory,
                'request' => $this->requestMock,
                'orderRepository' => $this->orderRepository,
                'paymentApiClient' => $this->paymentApiClientMock,
                'invoice' => $this->invoice,
                'checkoutSession' => $this->checkoutSession,
            ]
        );
    }

    /**
     * @return array
     */
    protected function getAddresData(): array
    {
        return [
            'region' => 'CA',
            'region_id' => '12',
            'postcode' => '11111',
            'company' => 'Test Company',
            'lastname' => 'lastname',
            'firstname' => 'firstname',
            'street' => 'street',
            'city' => 'Los Angeles',
            'email' => 'admin@example.com',
            'telephone' => '11111111',
            'country_id' => 'NL'
        ];
    }

    /**
     * @return array
     */
    protected function getTaxRates(): array
    {
        return [
            'data' =>
                [
                    [
                        'id' => 55,
                        'name' => 'Netherlands (standard)',
                        'alias' => 'standard',
                        'value' => 0.21,
                        'country_iso2' => 'NL',
                    ],
                    [
                        'id' => 56,
                        'name' => 'Netherlands (reduced: food / books / pharma / medical / hotels)',
                        'alias' => 'reduced_books_food_hotels_medical_pharma',
                        'value' => 0.06,
                        'country_iso2' => 'NL',
                    ],
                    [
                        'id' => 57,
                        'name' => 'Netherlands (reduced: food / books / pharma / medical / hotels - 2019)',
                        'alias' => 'reduced_2019_books_food_hotels_medical_pharma',
                        'value' => 0.09,
                        'country_iso2' => 'NL',
                    ],
                ],
            'total' => 3,
            'count' => 3,
        ];
    }

    /**
     * @return array
     */
    protected function getStoreKeeperOrder(): array
    {
        return [
            'id' => self::STORE_KEEPER_ORDER_ID,
            'number' => self::STORE_KEEPER_ORDER_NUMBER
        ];
    }

    /**
     * @return Customer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getCustomer(): Customer
    {
        /** @var CustomerRegistry $customerRegistry */
        $customerRegistry = Bootstrap::getObjectManager()->create(CustomerRegistry::class);

        return $customerRegistry->retrieve(1);
    }

    /**
     * @return ProductRepositoryInterface
     */
    protected function getProductRepository(): ProductRepositoryInterface
    {
        return Bootstrap::getObjectManager()->create(ProductRepositoryInterface::class);
    }

    /**
     * @return ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProduct(): ProductInterface
    {
        return $this->getProductRepository()->get(self::PRODUCT_SKU);
    }

    /**
     * @return Address
     */
    protected function getBillingAddress(): Address
    {
        $billingAddress = Bootstrap::getObjectManager()->create(Address::class, ['data' => $this->getAddresData()]);
        $billingAddress->setAddressType('billing');

        return $billingAddress;
    }

    /**
     * @return QuoteAddressInterface
     */
    protected function getQuoteBillingAddress(): QuoteAddressInterface
    {
        $quoteBillingAddress = Bootstrap::getObjectManager()->create(QuoteAddressInterface::class, ['data' => $this->getAddresData()]);
        $quoteBillingAddress->setAddressType('billing');

        return $quoteBillingAddress;
    }

    /**
     * @return TaxClassModel
     * @throws \Exception
     */
    protected function getTaxClass(): TaxClassModel
    {
        $taxClassName = 'Test Tax Class';
        $taxClassType = TaxClassModel::TAX_CLASS_TYPE_PRODUCT;
        $taxClass = Bootstrap::getObjectManager()->create(TaxClassModel::class);
        $taxClass->setClassName($taxClassName);
        $taxClass->setClassType($taxClassType);
        $taxClass->save();

        return $taxClass;
    }

    /**
     * @return TaxRateCalculation
     * @throws \Exception
     */
    protected function getTaxRate(): TaxRateCalculation
    {
        $taxRateData = [
            'tax_country_id' => 'NL',
            'tax_region_id' => '0',
            'tax_postcode' => '*',
            'code' => 'Test Tax Rate',
            'rate' => '21.0000',
        ];
        $taxRate = Bootstrap::getObjectManager()->create(TaxRateCalculation::class);
        $taxRate->setData($taxRateData);
        $taxRate->save();

        return $taxRate;
    }

    /**
     * @param TaxRateCalculation $taxRate
     * @param string $taxClassId
     * @throws \Exception
     */
    protected function createTaxRule(TaxRateCalculation $taxRate, string $taxClassId): void
    {
        $taxRuleData = [
            'code' => 'Test Tax Rule',
            'tax_rate_ids' => [$taxRate->getId()],
            'customer_tax_class_ids' => [3], // Customer tax class IDs if needed
            'product_tax_class_ids' => [$taxClassId], // Product tax class IDs if needed
            'priority' => 0
        ];
        $taxRule = Bootstrap::getObjectManager()->create(TaxRuleCalculation::class);
        $taxRule->setData($taxRuleData);
        $taxRule->save();
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    protected function createTestOrderWithPayment(bool $isGuest): string
    {
        //Retrieve product from repository
        $product = $this->getProduct();
        $product->setOptions(null);
        $taxClassId = $this->getTaxClass()->getId();
        $this->createTaxRule($this->getTaxRate(), $taxClassId);
        $product->setTaxClassId($taxClassId);
        $this->getProductRepository()->save($product);

        //Create empty cart and get cart id
        $cartId = $this->createEmptyCartForCustomer($isGuest);

        //Add item to newly created guest customer cart
        $quoteItem = Bootstrap::getObjectManager()->create(CartItemInterface::class);
        $quoteItem->setQuoteId($cartId);
        $quoteItem->setProduct($product);
        $quoteItem->setQty(2);
        $this->cartItemRepository->save($quoteItem);

        //Fill out address data
        $billingAddress = Bootstrap::getObjectManager()->create(
            QuoteAddressInterface::class,
            [
                'data' => $this->getAddresData()
            ]
        );
        $shippingAddress = clone $billingAddress;
        $shippingAddress->setSameAsBilling(true);
        $this->shippingAddressManagement->assign($cartId, $shippingAddress);
        $shippingAddress = $this->shippingAddressManagement->get($cartId);

        //Determine shipping options and collect totals
        $totals = Bootstrap::getObjectManager()->create(TotalsInformationInterface::class);
        $totals->setAddress($shippingAddress);
        $totals->setShippingCarrierCode('flatrate');
        $totals->setShippingMethodCode('flatrate');
        $this->totalsInformationManagement->calculate($cartId, $totals);

        //Select payment method
        $payment = Bootstrap::getObjectManager()->create(PaymentInterface::class);
        $payment->setMethod('storekeeper_payment_ideal');
        $this->paymentMethodManagement->set($cartId, $payment);
        $quote = $this->cartRepository->get($cartId);

        //Verify checkout session contains correct quote data
        $this->checkoutSession->clearQuote();
        $this->checkoutSession->setQuoteId($quote->getId());

        //Set up persistent session data and expire customer session
        $this->persistentSessionHelper->getSession()
            ->setPersistentCookie(10000, '');
        $this->persistentSessionHelper->getSession()->removePersistentCookie()->setPersistentCookie(100000000, '');
        $this->customerSession->setIsCustomerEmulated(true)->expireSessionCookie();

        //Submit order as expired/emulated customer
        //Grab created order data
        $paymentManagement = Bootstrap::getObjectManager()->create(
            PaymentInformationManagement::class
        );
        $orderId = $paymentManagement->savePaymentInformationAndPlaceOrder(
            $this->checkoutSession->getQuote()->getId(),
            $quote->getPayment(),
            $billingAddress
        );

        return $orderId;
    }

    /**
     * @param bool $isGuest
     * @return string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function createEmptyCartForCustomer(bool $isGuest): string
    {
        if ($isGuest) {
            $maskId = $this->guestCartManagement->createEmptyCart();
            $cartId = $this->maskedQuoteIdToQuoteId->execute($maskId);
        } else {
            $customer = $this->getCustomer();
            $this->customerSession->loginById($customer->getId());
            $cartId = $this->cartManagement->createEmptyCartForCustomer($customer->getId());
        }

        return $cartId;
    }

    /**
     * @param bool $isGuest
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    protected function executeOrderWithPayment(bool $isGuest): void
    {
        // Create test order and get orderId
        $orderId = $this->createTestOrderWithPayment($isGuest);

        // Execute Redirect and Finish controllers
        $this->redirect->execute();
        $this->finish->execute();

        // Assert order state to 'processing' state
        $order = $this->orderRepository->get($orderId);
        $this->assertEquals($order->getState(), Order::STATE_PROCESSING);
    }

    /**
     * @param bool $isGuest
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     * @throws \Magento\Framework\Exception\State\InvalidTransitionException
     */
    protected function executeRefundOrderWithPayment(bool $isGuest): void
    {
        // Create test order
        $orderId = $this->createTestOrderWithPayment($isGuest);
        $order = $this->orderRepository->get($orderId);

        //Set total refund value
        $order->setTotalRefunded(90.00);
        $this->orderRepository->save($order);

        //Apply order refund
        $this->cronOrders->execute();
    }

    /**
     * @param string $method
     * @return Payment
     */
    protected function createPayment(string $method): Payment
    {
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setMethod($method);

        return $payment;
    }

    /**
     * @param ProductInterface $product
     * @return Item
     */
    protected function createOrderItem(ProductInterface $product): Item
    {
        $orderItem = Bootstrap::getObjectManager()->create(Item::class);
        $requestInfo = [
            'qty' => 1
        ];
        $orderItem->setProductId($product->getId())
            ->setTaxAmount(2.31)
            ->setTaxPercent(21)
            ->setQtyOrdered(1)
            ->setBasePrice($product->getPrice())
            ->setPrice($product->getPrice())
            ->setOriginalPrice(11)
            ->setPriceInclTax(13.31)
            ->setRowTotal($product->getPrice())
            ->setProductType($product->getTypeId())
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setStoreId(0)
            ->setProductId($product->getId())
            ->setSku($product->getSku())
            ->setProductOptions(['info_buyRequest' => $requestInfo]);

        return $orderItem;
    }

    /**
     * @param Address $billingAddress
     * @param Address $shippingAddress
     * @param null|Customer $customer
     * @param Payment $payment
     * @param Item $orderItem
     * @return Order
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function createOrder(Address $billingAddress, Address $shippingAddress, ?Customer $customer, Payment $payment, Item $orderItem): Order
    {
        $order = Bootstrap::getObjectManager()->create(Order::class);
        $order->setIncrementId(self::ORDER_INCREMENT_ID);
        $order->setState(Order::STATE_NEW);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_NEW));
        $order->setCustomerIsGuest(!$customer);
        $order->setCustomerEmail($customer ? $customer->getEmail() : 'customer@null.com');
        $order->setCustomerFirstname($customer ? $customer->getName() : 'firstname');
        $order->setCustomerLastname($customer ? $customer->getLastname() : 'lastname');
        $order->setBillingAddress($billingAddress);
        $order->setShippingAddress($shippingAddress);
        $order->setAddresses([$billingAddress, $shippingAddress]);
        $order->setPayment($payment);
        $order->addItem($orderItem);
        $order->setStoreId(Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getId());
        $order->setSubtotal(100);
        $order->setBaseSubtotal(100);
        $order->setBaseGrandTotal(100);
        $order->setStoreCurrencyCode('USD');
        $order->setStorekeeperPaymentId(random_int(1, 100));

        return $order;
    }

    /**
     * @param Order $order
     * @return void
     */
    protected function assertOrderCreation(Order $order): void
    {
        $this->orderRepository->save($order);
        $this->assertEquals(1, $order->getStorekeeperOrderPendingSync());
        $this->assertEquals(Order::STATE_NEW, $order->getState());

        $this->cronOrders->execute();
        $savedOrder = $this->orderRepository->get('1');

        $this->assertEquals(self::STORE_KEEPER_ORDER_ID, $savedOrder->getStorekeeperId());
        $this->assertEquals(self::STORE_KEEPER_ORDER_NUMBER, $savedOrder->getStorekeeperOrderNumber());
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getOrderData(): array
    {
        $billingAddress = $this->getBillingAddress();
        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');
        $product = $this->getProduct();
        $payment = $this->createPayment('checkmo');
        $orderItem = $this->createOrderItem($product);

        return [
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
            'payment' => $payment,
            'orderItem' => $orderItem
        ];
    }
}
