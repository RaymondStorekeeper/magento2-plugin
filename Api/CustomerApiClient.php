<?php

namespace StoreKeeper\StoreKeeper\Api;

use Magento\Customer\Model\Data\Customer;
use Magento\Sales\Model\Order;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\StoreKeeper\Api\OrderApiClient;
use Psr\Log\LoggerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\AddressFactory;
use Magento\Framework\Model\AbstractExtensibleModel;

class CustomerApiClient
{
    private OrderApiClient $orderApiClient;
    private LoggerInterface $logger;
    private CustomerRepositoryInterface $customerRepository;
    private AddressFactory $addressFactory;

    /**
     * CustomerApiClient constructor.
     * @param OrderApiClient $orderApiClient
     * @param LoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        OrderApiClient $orderApiClient,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        AddressFactory $addressFactory
    ) {
        $this->orderApiClient = $orderApiClient;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @param string $storeId
     * @param string $email
     * @return array|null
     * @throws \Exception
     */
    public function findShopCustomerBySubuserEmail(string $storeId, string $email): ?array
    {
        return $this->orderApiClient->getShopModule($storeId)->findShopCustomerBySubuserEmail(['email' => $email]);
    }

    /**
     * Create StoreKeeper customer by order
     *
     * @param string $email
     * @param Order $order
     * @return int
     */
    public function createStorekeeperCustomerByOrder(string $email, Order $order): int
    {
        if (!$order->getCustomerIsGuest()) {
            $customer = $this->customerRepository->getById($order->getCustomerId());
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
                    'login' => $email,
                    'email' => $email
                ]
            ]
        ];

        $relationDataId = (int)$this->getNewShopCustomer($data, $order->getStoreId());

        return $relationDataId;
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

        $relationDataId = (int)$this->getNewShopCustomer($data, $customer->getStoreId());

        return $relationDataId;
    }

    /**
     * Get Default Billing Address
     *
     * @param Customer $customer
     * @return Address
     */
    private function getDefaultBillingAddress(Customer $customer): Address
    {
        $billingAddressId = $customer->getDefaultBilling();

        return $this->addressFactory->create()->load($billingAddressId);
    }

    /**
     * Get Default Shipping Address
     *
     * @param Customer $customer
     * @return Address
     */
    private function getDefaultShippingAddress(Customer $customer): Address
    {
        $shippingAddressId = $customer->getDefaultShipping();

        return $this->addressFactory->create()->load($shippingAddressId);
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

    /**
     * Map address
     *
     * @param AbstractExtensibleModel $address
     * @return array
     */
    public function mapAddress(AbstractExtensibleModel $address): array
    {
        return [
            'name' => $address->getName(),
            'city' => $address->getCity(),
            'zipcode' => $address->getPostcode(),
            'street' => implode(', ', $address->getStreet()),
            'country_iso2' => $address->getCountryId(),
        ];
    }

    public function getNewShopCustomer(array $data, string $storeId)
    {
        return $this->orderApiClient->getShopModule($storeId)->newShopCustomer($data);
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
}
