<?php

namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use StoreKeeper\ApiWrapper\Exception\GeneralException;

/**
 * @depracated
 */
class Customers extends AbstractHelper
{
    private const SEPARATE_STREET_NAME_AND_NUMBER_PATTERN = "/\A(.*?)\s+(\d+[a-zA-Z]{0,1}\s{0,1}[-]{1}\s{0,1}\d*[a-zA-Z]{0,1}|\d+[a-zA-Z-]{0,1}\d*[a-zA-Z]{0,1})/";
    private Auth $authHelper;
    private AddressFactory $addressFactory;
    private CustomerRepositoryInterface $customerRepositoryInterface;
    private Context $context;
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param AddressFactory $addressFactory
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param Context $context
     * @param LoggerInterface $logger
     */
    public function __construct(
        Auth $authHelper,
        AddressFactory $addressFactory,
        CustomerRepositoryInterface $customerRepositoryInterface,
        Context $context,
        LoggerInterface $logger
    ) {
        $this->authHelper = $authHelper;
        $this->addressFactory = $addressFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->logger = $logger;

        parent::__construct($context);
    }

    /**
     * Create StoreKeeper customer
     *
     * @param Customer $customer
     * @return int
     */
    public function createStoreKeeperCustomer(Customer $customer): int
    {
        $billingAddress = $this->getDefaultBillingAddress($customer);
        $shippingAddress = $this->getDefaultShippingAddress($customer);

        if (!$shippingAddress) {
            $shippingAddress = $billingAddress;
        }

        $data = [
            'relation' => [
                'business_data' => $this->getBusinessData($customer),
                'contact_person' => [
                    'familyname' => $customer->getLastname(),
                    'firstname' => $customer->getFirstname(),
                    'contact_set' => [
                        'email' => $customer->getEmail(),
                        'phone' => '',
                        'name' => $customer->getLastname()
                    ]
                ],
                'contact_set' => $this->getContactSetFromCustomer($customer),
                'contact_address' => $this->mapAddress($shippingAddress),
                'address_billing' => $this->mapAddress($billingAddress),
                'subuser' => [
                    'login' => $customer->getEmail(),
                    'email' => $customer->getEmail()
                ]
            ]
        ];

        $relationDataId = $this->authHelper->getModule(
            'ShopModule',
            $customer->getStoreId()
        )->newShopCustomer($data);

        return (int) $relationDataId;
    }

    /**
     * Create StoreKeeper customer by order
     *
     * @param $order Order
     * @return int
     */
    public function createStorekeeperCustomerByOrder(Order $order): int
    {
        if (!$order->getCustomerIsGuest()) {
            $customer = $this->customerRepositoryInterface->getById($order->getCustomerId());
            return $this->createStoreKeeperCustomer($customer);
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            $shippingAddress = $billingAddress;
        }

        $data = [
            'relation' => [
                'business_data' => $this->getBusinessDataFromOrder($order),
                'contact_person' => $this->getContactPersonFromOrder($order),
                'contact_set' => $this->getContactSetFromOrder($order),
                'contact_address' => $this->mapAddress($shippingAddress),
                'address_billing' => $this->mapAddress($billingAddress),
                'subuser' => [
                    'login' => $order->getCustomerEmail(),
                    'email' => $order->getCustomerEmail()
                ]
            ]
        ];

        $relationDataId = $this->authHelper->getModule(
            'ShopModule',
            $order->getStoreId()
        )->newShopCustomer($data);

        return (int) $relationDataId;
    }

    /**
     * Get Business data from order
     *
     * @param Order $order
     * @return array|null
     */
    private function getBusinessDataFromOrder(Order $order): ?array
    {
        $companyName = $order->getBillingAddress()->getCompany();
        if (!empty($companyName)) {
            return [
                'name' => $companyName,
                'country_iso2' => $order->getBillingAddress()->getCountryId()
            ];
        }

        return null;
    }

    /**
     * Get contact person from order
     *
     * @param Order $order
     * @return array
     */
    private function getContactPersonFromOrder(Order $order): array
    {
        return [
            'familyname' => $order->getCustomerLastname(),
            'firstname' => $order->getCustomerFirstname(),
            'contact_set' => $this->getContactSetFromOrder($order)
        ];
    }

    /**
     * Map address
     *
     * @param $address
     * @return array
     */
    public function mapAddress($address): array
    {
        return [
            'name' => $address->getName(),
            'city' => $address->getCity(),
            'zipcode' => $address->getPostcode(),
            'street' => implode(', ', $address->getStreet()),
            'country_iso2' => $address->getCountryId(),
        ];
    }

    /**
     * Get contact set from order
     *
     * @param Order $order
     * @return array
     */
    private function getContactSetFromOrder(Order $order): array
    {
        return [
            'email' => $order->getCustomerEmail(),
            'phone' => $order->getBillingAddress()->getTelephone(),
            'name' => $order->getCustomerName()
        ];
    }

    /**
     * Get business data
     *
     * @param Customer $customer
     * @return array|null
     */
    private function getBusinessData(Customer $customer): ?array
    {
        $billingAddress = $this->getDefaultBillingAddress($customer);
        $companyName = $billingAddress->getCompany();

        if (!empty($companyName)) {
            return [
                'name' => $companyName,
                'country_iso2' => $billingAddress->getCountryId()
            ];
        }

        return null;
    }

    /**
     * Get Default Billing Address
     *
     * @param $customer
     * @return \Magento\Customer\Model\Address
     */
    private function getDefaultBillingAddress($customer): \Magento\Customer\Model\Address
    {
        $billingAddressId = $customer->getDefaultBilling();

        return $this->addressFactory->create()->load($billingAddressId);
    }

    /**
     * Get Default Shipping Address
     *
     * @param $customer
     * @return \Magento\Customer\Model\Address
     */
    private function getDefaultShippingAddress($customer): \Magento\Customer\Model\Address
    {
        $shippingAddressId = $customer->getDefaultShipping();

        return $this->addressFactory->create()->load($shippingAddressId);
    }

    /**
     * Get contact set from customer
     *
     * @param Customer $customer
     * @return array
     */
    private function getContactSetFromCustomer(Customer $customer): array
    {
        $billingAddress = $this->getDefaultBillingAddress($customer);

        return [
            'email' => $customer->getEmail(),
            'phone' => $billingAddress->getTelephone(),
            'name' => $customer->getLastname()
        ];
    }
}
