<?php

namespace StoreKeeper\StoreKeeper\Test\Integration;

use Magento\Sales\Model\Order;
use StoreKeeper\StoreKeeper\Test\Integration\AbstractTest;

class OrderWithPaymentTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/product_simple_without_custom_options.php
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/customer.php
     * @magentoConfigFixture current_store payment/storekeeper_payment_ideal/active 1
     */
    public function testPayment()
    {
        $this->executeOrderWithPayment(false);
    }
}
