<?php

namespace StoreKeeper\StoreKeeper\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use StoreKeeper\StoreKeeper\Helper\Api\Auth;
use StoreKeeper\StoreKeeper\Api\OrderApiClient;

class Index extends Action implements HttpGetActionInterface
{
    private Http $request;
    private Auth $authHelper;
    private Url $url;
    private OrderApiClient $orderApiClient;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Http $request
     * @param Auth $authHelper
     * @param ManagerInterface $messageManager
     * @param Url $url
     * @param OrderApiClient $orderApiClient
     */
    public function __construct(
        Context $context,
        Http $request,
        Auth $authHelper,
        ManagerInterface $messageManager,
        Url $url,
        OrderApiClient $orderApiClient
    ) {
        parent::__construct($context);

        $this->request = $request;
        $this->authHelper = $authHelper;
        $this->messageManager = $messageManager;
        $this->url = $url;
        $this->orderApiClient = $orderApiClient;
    }

    /**
     * Create validation exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for csrf
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * SK index action
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeId = $this->request->getParam('storeId');
        if ($this->authHelper->isConnected($storeId)) {
            try {
                $storeInformation = $this->orderApiClient->getStoreInformation($storeId);
                $this->authHelper->setStoreInformation($storeId, $storeInformation);
            } catch (\Exception $e) {
                $this->messageManager->addError(__($e->getMessage()));
            }
        } else {
            $this->messageManager->addError(__("Could not retrieve store information: not connected"));
        }

        return $this->_redirect('adminhtml/system_config/edit/section/storekeeper_general', ['store' => $storeId]);
    }

    /**
     * Index action allowed
     *
     * @return true
     */
    protected function _isAllowed()
    {
        return true;
    }
}
