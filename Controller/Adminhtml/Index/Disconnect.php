<?php

namespace StoreKeeper\StoreKeeper\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\Model\Url;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use StoreKeeper\StoreKeeper\Helper\Api\Auth;

class Disconnect extends Action implements HttpGetActionInterface
{
    private Http $request;
    private Auth $authHelper;
    private Url $url;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Http $request
     * @param Auth $authHelper
     * @param ManagerInterface $messageManager
     * @param Url $url
     */
    public function __construct(
        Context $context,
        Http $request,
        Auth $authHelper,
        ManagerInterface $messageManager,
        Url $url
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->authHelper = $authHelper;
        $this->messageManager = $messageManager;
        $this->url = $url;
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
     * SK disconnect action
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeId = $this->request->getParam('storeId');
        try {
            $this->authHelper->disconnectStore($storeId);
            $this->messageManager->addSuccess(__("Store {$storeId} has been disconnected from StoreKeeper"));
        } catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }

        return $this->_redirect('adminhtml/system_config/edit/section/storekeeper_general', ['store' => $storeId]);
    }

    /**
     * Disconnect action allowed
     *
     * @return true
     */
    protected function _isAllowed()
    {
        return true;
    }
}
