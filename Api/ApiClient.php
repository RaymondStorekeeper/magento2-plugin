<?php

namespace StoreKeeper\StoreKeeper\Api;

use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\ApiWrapper\Wrapper\FullJsonAdapter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use StoreKeeper\ApiWrapper\ModuleApiWrapperInterface;
use StoreKeeper\ApiWrapper\Auth;

class ApiClient
{
    private ScopeConfigInterface $scopeConfig;

    /**
     * ApiClient constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->auth = null;
    }

    /**
     * Get SK adapter
     *
     * @param string $storeId
     * @return FullJsonAdapter
     * @throws \Exception
     */
    private function getAdapter(string $storeId): FullJsonAdapter
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

    /**
     * Obtain module entity from SK API wrapper
     *
     * @param string $module
     * @param string $storeId
     * @return ModuleApiWrapperInterface
     * @throws \Exception
     */
    protected function getModule(string $module, string $storeId): ModuleApiWrapperInterface
    {
        $api = new ApiWrapper($this->getAdapter($storeId), $this->getAuthWrapper($storeId));
        return $api->getModule($module);
    }

    /**
     * Authorize SK API wrapper
     *
     * @param string $storeId
     * @return Auth
     * @throws \Exception
     */
    private function getAuthWrapper(string $storeId): Auth
    {
        if (is_null($this->auth)) {
            $syncAuth = $this->getSyncAuth($storeId);
            if (empty($syncAuth)) {
                throw new \Exception(
                    "Unable to authenticate with StoreKeeper. Did you add your API key to your store?"
                );
            }
            $this->auth = new \StoreKeeper\ApiWrapper\Auth();
            $this->auth->setSubuser($syncAuth['subaccount'], $syncAuth['user']);
            $this->auth->setApiKey($syncAuth['apikey']);
            $this->auth->setAccount($syncAuth['account']);
        }
        return $this->auth;
    }

    /**
     * Get SK sync auth config value
     *
     * @param string $storeId
     * @return array|null
     */
    private function getSyncAuth(string $storeId): ?array
    {
        $syncAuth = $this->getScopeConfigValue(
            "storekeeper_general/general/storekeeper_sync_auth",
            $storeId,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES
        );
        if (!empty($syncAuth)) {
            return json_decode($syncAuth, true);
        }
        return null;
    }

    /**
     * Get Scope config value
     *
     * @param string $key
     * @param $id
     * @param $scope
     * @return string|null
     */
    private function getScopeConfigValue(
        string $key,
        $id = 0,
        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    ): ?string {
        return $this->scopeConfig->getValue(
            $key,
            $scope,
            $id
        );
    }
}
