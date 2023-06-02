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

class AbstractGuestTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}
