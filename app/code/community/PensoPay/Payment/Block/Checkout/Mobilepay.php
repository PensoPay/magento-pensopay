<?php

class PensoPay_Payment_Block_Checkout_Mobilepay extends Mage_Core_Block_Template
{
    const MOBILEPAY_ACTICE_XML_PATH      = 'payment/pensopay_mobilepay_checkout/active';
    const MOBILEPAY_TITLE_XML_PATH      = 'payment/pensopay_mobilepay_checkout/title';
    const MOBILEPAY_DESCRIPTION_XML_PATH  = 'payment/pensopay_mobilepay_checkout/instructions';
    const MOBILEPAY_POPUP_DESCRIPTION_XML_PATH  = 'payment/pensopay_mobilepay_checkout/popup_description';

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return Mage::getStoreConfig(self::MOBILEPAY_TITLE_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return Mage::getStoreConfig(self::MOBILEPAY_DESCRIPTION_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function getPopupDescription()
    {
        return Mage::getStoreConfig(self::MOBILEPAY_POPUP_DESCRIPTION_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return mixed
     */
    public function isActive()
    {
        return Mage::getStoreConfig(self::MOBILEPAY_ACTICE_XML_PATH, Mage::app()->getStore());
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->getUrl('pensopay/mobilepay/redirect');
    }

    /**
     * @return mixed
     */
    public function getShippingMethods()
    {
        return Mage::getModel('pensopay/carrier_shipping')->getMobilePayMethods();
    }
}