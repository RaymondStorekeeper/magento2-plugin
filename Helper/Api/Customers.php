<?php

namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use Magento\Sales\Model\Order\Address;

class Customers extends AbstractHelper
{
    private const SEPARATE_STREET_NAME_AND_NUMBER_PATTERN = "/\A(.*?)\s+(\d+[a-zA-Z]{0,1}\s{0,1}[-]{1}\s{0,1}\d*[a-zA-Z]{0,1}|\d+[a-zA-Z-]{0,1}\d*[a-zA-Z]{0,1})/";

    private $authHelper;

    private AddressFactory $addressFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param AddressFactory $addressFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
     * @param Context $context
     * @param LoggerInterface $logger
     */
    public function __construct(
        Auth $authHelper,
        AddressFactory $addressFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
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
     * @param $email
     * @param $storeId
     * @return false|int
     */
    public function findCustomerRelationDataIdByEmail($email, $storeId)
    {
        $id = false;
        if (!empty($email)) {
            try {
                $customer = $this->authHelper->getModule('ShopModule', $storeId)->findShopCustomerBySubuserEmail([
                    'email' => $email
                ]);
                $id = (int)$customer['id'];
            } catch (GeneralException $exception) {
                // Customer not found in StoreKeeper
                $this->logger->error($exception->getMessage());
            }
        }

        return $id;
    }

    /**
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

        $relationDataId = $this->authHelper->getModule('ShopModule', $customer->getStoreId())->newShopCustomer($data);

        return (int) $relationDataId;
    }

    /**
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

        $relationDataId = $this->authHelper->getModule('ShopModule', $order->getStoreId())->newShopCustomer($data);

        return (int) $relationDataId;
    }

    /**
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
     * @param $address
     * @return array
     */
    public function mapAddress($address): array
    {
        $streetData = $this->getStreetData($address);
        return [
            'name' => $address->getName(),
            'city' => $address->getCity(),
            'zipcode' => $address->getPostcode(),
            'street' => $streetData[0],
            'streetnumber' => $streetData[1],
            'country_iso2' => $address->getCountryId(),
        ];
    }

    /**
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

    private function getDefaultBillingAddress($customer): \Magento\Customer\Model\Address
    {
        $billingAddressId = $customer->getDefaultBilling();

        return $this->addressFactory->create()->load($billingAddressId);
    }

    private function getDefaultShippingAddress($customer): \Magento\Customer\Model\Address
    {
        $shippingAddressId = $customer->getDefaultShipping();

        return $this->addressFactory->create()->load($shippingAddressId);
    }

    /**
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
     * @param Address $address
     * @return array
     */
    private function getStreetData(Address $address): array
    {
        $streetData = $address->getStreet();
        if (count($streetData) > 1) {
            return $streetData;
        }

        preg_match(self::SEPARATE_STREET_NAME_AND_NUMBER_PATTERN, $streetData[0], $streetData);
        array_shift($streetData);

        return $streetData;
    }
}
