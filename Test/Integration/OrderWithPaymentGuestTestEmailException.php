<?php

namespace StoreKeeper\StoreKeeper\Test\Integration;

use Magento\Sales\Model\Order;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\StoreKeeper\Test\Integration\AbstractTest;

class OrderWithPaymentGuestTest extends AbstractGuestTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $ex = new GeneralException('Email not found', 0);
        $ex->setApiExceptionClass('ShopModule::EmailIsAdminUser');
        $this->customerApiClientMock->method('findShopCustomerBySubuserEmail')->willThrowException($ex);
    }

    /**
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/product_simple_without_custom_options.php
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/customer.php
     * @magentoConfigFixture current_store payment/storekeeper_payment_ideal/active 1
     */
    public function testGuestPayment()
    {
        $this->executeOrderWithPayment(true);
    }
}
