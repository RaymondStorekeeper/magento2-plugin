<?php

namespace StoreKeeper\StoreKeeper\Test\Integration;

use StoreKeeper\StoreKeeper\Test\Integration\AbstractTest;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class OrderCreationTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/product_simple_without_custom_options.php
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/customer.php
     * @magentoConfigFixture current_store storekeeper_general/general/enabled 1
     * @magentoConfigFixture current_store storekeeper_general/general/storekeeper_sync_auth {"rights":"subuser","mode":"apikey","account":"centroitbv","subaccount":"64537ca6-18ae-41e5-a6a9-20b803f97117","user":"sync","apikey":"REDACTED"}
     * @magentoConfigFixture current_store storekeeper_general/general/storekeeper_sync_mode 4
     
     *The function 'testOrderCreation()', uses certain fixtures to get a basic customer, product, and order with respecting data for testing, it pulls it from the '$this' variable.
     *afterwards, the variable '$order' is filled with the 'createOrder()' function, which uses data from the '$orderdata' variable
     *then it runs the actual test function called 'assertOrderCreation($order)'
    */
    
    public function testOrderCreation()
    {
        $customer = $this->getCustomer();
        $orderData = $this->getOrderData();
        $order = $this->createOrder(
            $orderData['billingAddress'],
            $orderData['shippingAddress'],
            $customer,
            $orderData['payment'],
            $orderData['orderItem']
        );
        $this->assertOrderCreation($order);
    }
}
