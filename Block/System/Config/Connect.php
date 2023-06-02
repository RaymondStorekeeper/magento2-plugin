<?php

namespace StoreKeeper\StoreKeeper\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\Element\AbstractElement;
use StoreKeeper\StoreKeeper\Helper\Api\Auth;

class Connect extends Field
{
    protected $_template = 'StoreKeeper_StoreKeeper::system/config/connect.phtml';
    private Auth $authHelper;
    private Http $request;

    /**
     * Constructor
     *
     * @param Auth $authHelper
     * @param Http $request
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Auth $authHelper,
        Http $request,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->authHelper = $authHelper;
        $this->request = $request;
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get SK connect url
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getConnectUrl(): string
    {
        $storeId = $this->request->getParam('store');
        $token = $this->authHelper->generateToken($storeId);
        $redirectUrl = $this->authHelper->getInitializeUrl($storeId, $token);
        return $redirectUrl;
    }

    /**
     * Generate connect button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $storeId = $this->request->getParam('store');
        if ($this->authHelper->isConnected($storeId)) {
            $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData(
                    ['id' => 'connect_button',
                        'label' => __('StoreKeeper Connect'),
                        'disabled' => 'disabled'
                    ]
                );
        } else {
            $url = $this->getConnectUrl();
            $button = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData(
                [
                    'id' => 'connect_button',
                    'label' => __('StoreKeeper Connect'),
                    'onclick' => 'setLocation(\'' . $url . '\')'
                ]
            );
        }
        return $button->toHtml();
    }
}
