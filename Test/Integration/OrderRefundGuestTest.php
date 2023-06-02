<?php

namespace StoreKeeper\StoreKeeper\Test\Integration;

use StoreKeeper\StoreKeeper\Test\Integration\AbstractTest;

class OrderRefundGuestTest extends AbstractGuestTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentApiClientMock->expects($this->once())
            ->method('attachPaymentIdsToOrder')
            ->with('1', 55, [33]);
    }

    /**
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/product_simple_without_custom_options.php
     * @magentoDataFixture StoreKeeper_StoreKeeper::Test/Integration/_files/customer.php
     * @magentoConfigFixture current_store storekeeper_general/general/enabled 1
     * @magentoConfigFixture current_store storekeeper_general/general/storekeeper_sync_auth {"rights":"subuser","mode":"apikey","account":"centroitbv","subaccount":"64537ca6-18ae-41e5-a6a9-20b803f97117","user":"sync","apikey":"REDACTED"}
     * @magentoConfigFixture current_store storekeeper_general/general/storekeeper_sync_mode 4
     * @magentoConfigFixture current_store payment/storekeeper_payment_ideal/active 1
     */
    public function testGuestRefund()
    {
        $this->executeRefundOrderWithPayment(true);
    }
}
