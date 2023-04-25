<?php
namespace StoreKeeper\StoreKeeper\Helper\Api;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Module\ModuleList;
use Magento\Store\Model\StoreManagerInterface;
use Ramsey\Uuid\Generator\PeclUuidRandomGenerator;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\ApiWrapper\Wrapper\FullJsonAdapter;

class Auth extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $storeShopIds = null;
    private const MODULE_NAME = 'StoreKeeper_StoreKeeper';
    private const MODULE_VENDOR = 'StoreKeeper';
    private const PLATFORM_NAME = 'Magento2';
    private const SOFTWARE_NAME = 'magento2-plugin';
    private const INTEGRATIONS_INITIALIZE_URL = "https://integrations.storekeeper.software/sk_connect/sales_channel/init_plugin_connect";

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        TypeListInterface $cache,
        Random $random,
        PeclUuidRandomGenerator $uuidRandomGenerator,
        ProductMetadataInterface $productMetadata,
        ModuleList $moduleList
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
        $this->cache = $cache;
        $this->random = $random;
        $this->uuidRandomGenerator = $uuidRandomGenerator;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
    }

    public function setAuthDataForWebsite($storeId, $authData)
    {
        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_sync_auth",
            json_encode($authData['sync_auth']),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_guest_auth",
            json_encode($authData['guest_auth']),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_shop_id",
            $authData['shop']['id'],
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_shop_name",
            $authData['shop']['name'],
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->cache->cleanType('config');
    }

    public function getStoreInformation($storeId)
    {
        return $this->getModule('ShopModule', $storeId)->getShopSettingsForHooks();
    }

    public function getTaxRates($storeId, $countryId)
    {
        return $this->getModule('ProductsModule', $storeId)->listTaxRates(
            0,
            100,
            null,
            [
                [
                    'name' => 'country_iso2__=',
                    'val' => $countryId
                ]
            ]
        );
    }

    public function setStoreInformation($storeId, array $data)
    {
        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_store_information",
            json_encode($data),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $this->cache->cleanType('config');
        return true;
    }

    public function authCheck($storeId)
    {
        $token = $this->getSecurityToken($storeId);

        if (empty($token)) {
            $token = md5(
                $storeId . uniqid()
            );
            $this->configWriter->save(
                "storekeeper_general/general/storekeeper_token",
                $token,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $this->cache->cleanType('config');
            header('location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
        }

        $json = json_encode(
            [
                'token' => $token, // Needs to the same over the applications lifespan.
                'webhook_url' => "{$this->getStoreBaseUrl()}/rest/V1/storekeeper/webhook?storeId={$storeId}", // Endpoint
            ]
        );

        $base64 = base64_encode($json);

        return $base64;
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getSecurityToken(?string $storeId = null)
    {
        return $this->getScopeConfigValue('storekeeper_general/general/storekeeper_token', $storeId);
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getInfoToken(?string $storeId = null)
    {
        return $this->getScopeConfigValue('storekeeper_general/general/storekeeper_info_token', $storeId);
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreBaseUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getConfigShopName($storeId)
    {
        return $this->getScopeConfigValue('general/store_information/name', $storeId);
    }

    /**
     * @param string $storeId
     * @return mixed
     */
    public function getShopUuid(string $storeId)
    {
        return $this->getScopeConfigValue('storekeeper_general/general/shop_uuid', $storeId);
    }

    /**
     * @return string
     */
    public function getVendor(): string
    {
        return self::MODULE_VENDOR;
    }

    /**
     * @return string
     */
    public function getPlatformName(): string
    {
        return self::PLATFORM_NAME;
    }

    /**
     * @return string
     */
    public function getSoftwareName(): string
    {
        return self::SOFTWARE_NAME;
    }

    /**
     * @return string
     */
    public function getIntegrationsInitializeUrl(): string
    {
        return self::INTEGRATIONS_INITIALIZE_URL;
    }

    public function getStoreShopIds()
    {
        if (is_null($this->storeShopIds)) {
            $this->storeShopIds = [];
            foreach ($this->storeManager->getStores() as $store) {
                $value = $this->getScopeConfigValue('storekeeper_general/general/storekeeper_shop_id', $store->getId());
                $this->storeShopIds[$value] = $store->getId();
            }
        }
        return $this->storeShopIds;
    }

    private $websiteShopIds = null;

    public function getWebsiteShopIds()
    {
        if (is_null($this->websiteShopIds)) {
            $this->websiteShopIds = [];
            foreach ($this->storeManager->getWebsites() as $website) {
                $value = $this->getScopeConfigValue(
                    'storekeeper_general/general/storekeeper_shop_id',
                    $website->getId(),
                    \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE
                );
                $this->websiteShopIds[$value] = $website->getId();
            }
        }
        return $this->websiteShopIds;
    }

    public function getAdapter($storeId)
    {
        $syncAuth = $this->getSyncAuth($storeId);
        $apiUrl = null;
        if (!empty($syncAuth) && isset($syncAuth['account'])) {
            $apiUrl = "https://api-{$syncAuth['account']}.storekeepercloud.com/";
        } else {
            throw new \Exception("An error occurred: Store #{$storeId} is not connected to StoreKeeper");
        }

        $adapter = new FullJsonAdapter($apiUrl);
        return $adapter;
    }

    public function getModule(string $module, $storeId)
    {
        $api = new ApiWrapper($this->getAdapter($storeId), $this->getAuthWrapper($storeId));
        return $api->getModule($module);
    }

    private $auth = null;

    public function getAuthWrapper($storeId)
    {
        if (is_null($this->auth)) {
            $sync_auth = $this->getSyncAuth($storeId);
            if (empty($sync_auth)) {
                throw new \Exception("Unable to authenticate with StoreKeeper. Did you add your API key to your store?");
            }

            $this->auth = new \StoreKeeper\ApiWrapper\Auth();
            $this->auth->setSubuser($sync_auth['subaccount'], $sync_auth['user']);
            $this->auth->setApiKey($sync_auth['apikey']);
            $this->auth->setAccount($sync_auth['account']);
        }

        return $this->auth;
    }

    public function isEnabled($storeId)
    {
        return $this->getScopeConfigValue(
            "storekeeper_general/general/enabled",
            $storeId,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES
        );
    }

    private function getSyncAuth($storeId)
    {
        $sync_auth = $this->getScopeConfigValue(
            "storekeeper_general/general/storekeeper_sync_auth",
            $storeId,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES
        );

        if (!empty($sync_auth)) {
            return json_decode($sync_auth, true);
        }
        return null;
    }

    public function isConnected($storeId)
    {
        return $this->isEnabled($storeId) && !empty($this->getSyncAuth($storeId));
    }

    public function disconnectStore($storeId)
    {
        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_sync_auth",
            null,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_guest_auth",
            null,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_shop_id",
            null,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_shop_name",
            null,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_store_information",
            null,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $this->cache->cleanType('config');
    }

    public function getLanguageForStore($storeId)
    {
        $lang = $this->getScopeConfigValue(
            'storekeeper_general/general/storekeeper_shop_language',
            $storeId,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES
        );

        if (empty($lang)) {
            $lang = ' ';
        }
        return $lang;
    }

    private function getScopeConfigValue(string $key, $id = 0, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
    {
        return $this->scopeConfig->getValue(
            $key,
            $scope,
            $id
        );
    }

    public function getInfoHookUrl(string $storeId, string $token): string
    {
        return "{$this->getStoreBaseUrl()}rest/V1/storekeeper/connectinfo?storeId={$storeId}&token=$token";
    }

    /**
     * @param string $storeId
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function generateInfoHookData(string $storeId): array
    {
        $shopUuid = ($this->getShopUuid($storeId))?$this->getShopUuid($storeId):$this->generateShopUuid($storeId);
        $info = $this->getShopInfo($storeId);
        $syncAuth = $this->getSyncAuth($storeId);
        $apiUrl = null;
        $accountName = null;

        if (!empty($syncAuth) && isset($syncAuth['account'])) {
            $accountName = $syncAuth['account'];
        }

        $infoHookDataArray = [
            'account_name' => $accountName,
            'shop' => $info,
            'webhook' => [
                'url' => "{$this->getStoreBaseUrl()}rest/V1/storekeeper/webhook?storeId={$storeId}",
                'auth' => 'header-token',
                'secret' => $this->getSecurityToken($storeId)
            ]
        ];

        return $infoHookDataArray;
    }

    /**
     * @param string $storeId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInitializeUrl(string $storeId, $token): string
    {
        $infoHookUrl = $this->getInfoHookUrl($storeId, $token);
        $url = $this->getIntegrationsInitializeUrl();
        $query = [
            'rest_info_url' => $infoHookUrl
        ];
        if( !empty($returnUrl)){
            $query['return_url'] = $returnUrl;
        }
        $redirectUrl = $url.'?'.http_build_query($query);

        return $redirectUrl;
    }

    /**
     * @param $storeId
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateToken($storeId): string
    {
        $token = $this->random->getRandomString(64);
        $this->configWriter->save(
            "storekeeper_general/general/storekeeper_token",
            $token,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
        $this->cache->cleanType('config');

        return $token;
    }

    /**
     * @param $storeId
     * @return string
     */
    public function generateShopUuid($storeId): string
    {
        $shopUuid = $this->uuidRandomGenerator->generate(64);
        $this->configWriter->save(
            "storekeeper_general/general/shop_uuid",
            $shopUuid,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        return $shopUuid;
    }

    /**
     * @param string $storeId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShopName(string $storeId): string
    {
        $configShopName = $this->getConfigShopName($storeId);
        if($configShopName) {
            $storeName = $configShopName;
        } else {
            $storeName = $this->storeManager->getStore()->getName();
        }

        return $storeName;
    }

    /**
     * @param string $storeId
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShopInfo(string $storeId): array
    {
        $module = $this->moduleList->getOne(self::MODULE_NAME);
        return $info = [
            "name" => $this->getShopName($storeId),
            "url" => $this->getStoreBaseUrl(),
            "alias" => $this->getShopUuid($storeId),
            "vendor" => $this->getVendor(),
            "platform_name" => $this->getPlatformName(),
            "platform_version" => $this->productMetadata->getVersion(),
            "software_name" => $this->getSoftwareName(),
            "software_version" => $module['setup_version']
        ];
    }
}
