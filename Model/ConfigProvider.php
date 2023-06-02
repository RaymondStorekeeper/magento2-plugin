<?php

namespace StoreKeeper\StoreKeeper\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use PHPUnit\Exception;
use StoreKeeper\ApiWrapper\Iterator\ListCallByIdPaginatedIterator;
use Magento\Payment\Model\Config;
use Magento\Theme\Block\Html\Header\Logo;
use StoreKeeper\StoreKeeper\Api\PaymentApiClient;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private $methodCodes = [
        'storekeeper_payment_alipay',
        'storekeeper_payment_amex',
        'storekeeper_payment_applepay',
        'storekeeper_payment_bataviacadeaukaart',
        'storekeeper_payment_billink',
        'storekeeper_payment_blik',
        'storekeeper_payment_cartebleue',
        'storekeeper_payment_creditclick',
        'storekeeper_payment_dankort',
        'storekeeper_payment_eps',
        'storekeeper_payment_fashioncheque',
        'storekeeper_payment_fashiongiftcard',
        'storekeeper_payment_gezondheidsbon',
        'storekeeper_payment_giropay',
        'storekeeper_payment_givacard',
        'storekeeper_payment_ideal',
        'storekeeper_payment_maestro',
        'storekeeper_payment_multibanco',
        'storekeeper_payment_nexi',
        'storekeeper_payment_overboeking',
        'storekeeper_payment_payconiq',
        'storekeeper_payment_paypal',
        'storekeeper_payment_paysafecard',
        'storekeeper_payment_postepay',
        'storekeeper_payment_przelewy24',
        'storekeeper_payment_spraypay',
        'storekeeper_payment_telefonischbetalen',
        'storekeeper_payment_visamastercard',
        'storekeeper_payment_wechatpay',
        'storekeeper_payment_yourgift',
        'storekeeper_payment'
    ];
    private $methods;
    private PaymentHelper $paymentHelper;
    private Config $paymentConfig;
    private Logo $logo;
    private PaymentApiClient $paymentApiClient;
    private StoreManagerInterface $storeManager;

    /**
     * ConfigProvider constructor.
     * @param PaymentHelper $paymentHelper
     * @param Config $paymentConfig
     * @param Logo $logo
     * @param PaymentApiClient $paymentApiClient
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Config $paymentConfig,
        Logo $logo,
        PaymentApiClient $paymentApiClient,
        StoreManagerInterface $storeManager
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->paymentConfig = $paymentConfig;
        $this->logo = $logo;
        $this->paymentApiClient = $paymentApiClient;
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $config = [];
        $config['storekeeper_payment_methods'] = $this->getMappedPaymentMethods();
        $config['magento_active_payment_methods'] = array_keys($this->paymentConfig->getActiveMethods());

        return $config;
    }

    /**
     * @return array
     */
    private function getStoreKeeperPaymentMethods(): array
    {
        $storeKeeperPaymentMethods =  $this->paymentApiClient->getListTranslatedPaymentMethodForHooks($this->storeManager->getStore()->getId());
        foreach ($storeKeeperPaymentMethods['data'] as $storeKeeperPaymentMethod) {
            if ($storeKeeperPaymentMethod['eid'] != 'Web::PaymentModule') {
                $paymentMethods[$storeKeeperPaymentMethod['id']] = [
                    'storekeeper_payment_method_id' => $storeKeeperPaymentMethod['id'],
                    'storekeeper_payment_method_title' => $storeKeeperPaymentMethod['title'],
                    'storekeeper_payment_method_logo_url' => $storeKeeperPaymentMethod['image_url'] ?? $this->logo->getLogoSrc(),
                    'storekeeper_payment_method_eId' => $storeKeeperPaymentMethod['eid']
                ];
            }
        }

        return $paymentMethods;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMappedPaymentMethods(): array
    {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = [
                'eId' => $this->paymentHelper->getMethodInstance($code)->getEId(),
                'code' => $this->paymentHelper->getMethodInstance($code)->getCode()
            ];
        }

        foreach ($this->getStoreKeeperPaymentMethods() as $storeKeeperPaymentMethod) {
            foreach ($this->methods as $code => $method) {
                if ($method['eId'] == $storeKeeperPaymentMethod['storekeeper_payment_method_eId']) {
                    $storeKeeperPaymentMethod['magento_payment_method_code'] = $code;
                }
            }
            $mappedPaymentMethods[] = $storeKeeperPaymentMethod;
        }

        return $mappedPaymentMethods;
    }
}
