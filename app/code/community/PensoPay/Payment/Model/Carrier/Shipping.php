<?php

class PensoPay_Payment_Model_Carrier_Shipping
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * @var string
     */
    protected $_code = 'pensopay_mobilepay';

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return bool|false|Mage_Core_Model_Abstract|Mage_Shipping_Model_Rate_Result|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if(!Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/active', $this->getStore()) || !Mage::app()->getRequest()->getParam('mobilepay')){
            return false;
        }
        $result = Mage::getModel('shipping/rate_result');
        $result->append($this->_getDefaultRate());

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            $this->_code => $this->getConfigData('name'),
        );
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    protected function _getDefaultRate()
    {
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier($this->_code);
        $rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setMethod($this->_code);
        $rate->setMethodTitle($this->getConfigData('name'));
        $rate->setPrice($this->getConfigData('price'));
        $rate->setCost(0);

        return $rate;
    }

    /**
     * @return bool
     */
    public function isTrackingAvailable(){
        return false;
    }

    /**
     * @return float
     */
    private function getShippingPrice()
    {
        $configPrice = $this->getConfigData('price');

        $shippingPrice = $this->getFinalPriceWithHandlingFee($configPrice);

        return $shippingPrice;
    }

    /**
     * @return array
     */
    private function getAvailableMethods(){
        return [
            'store_pick_up' => $this->getShipping1Title(),
            'home_delivery' => $this->getShipping2Title(),
            'registered_box' => $this->getShipping3Title(),
            'unregistered_box' => $this->getShipping4Title(),
            'pick_up_point' => $this->getShipping5Title(),
            'own_delivery' => $this->getShipping6Title()
        ];
    }

    /**
     * @return array
     */
    public function getMobilePayMethods(){
        $methods = $this->getAvailableMethods();
        $data = [];
        foreach($methods as $code => $title){
            $price = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_'.$code, $this->getStore());
            if(!$price){
                $price = 0;
            }
            $data[$code] = [
                'title' => $title,
                'price' => Mage::helper('core')->currency($price, true, false)
            ];
        }
        return $data;
    }

    /**
     * @param $code
     * @return array|bool
     */
    public function getMethodByCode($code){
        $methods = $this->getAvailableMethods();
        if(isset($methods[$code])){
            $price = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_'.$code, $this->getStore());
            if(!$price){
                $price = 0;
            }
            return [
                'title' => $methods[$code],
                'price' => number_format($price, 2)
            ];
        }
        return false;
    }

    public function getShipping1Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_store_pick_up_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Hent i butikken');
    }

    public function getShipping2Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_home_delivery_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Ordren leveres til din hjemmeadresse');
    }

    public function getShipping3Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_registered_box_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Afhentning i en pakkeshop (registered_box)');
    }

    public function getShipping4Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_unregistered_box_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Afhentning i en pakkeshop (unregistered_box)');
    }

    public function getShipping5Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_pick_up_point_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Afhentning i en pakkeshop (pick_up_point)');
    }

    public function getShipping6Title(){
        $title = Mage::getStoreConfig('payment/pensopay_mobilepay_checkout/shipping_own_delivery_title', $this->getStore());
        return $title ? $title : Mage::helper('pensopay')->__('Ordren leveres til din hjemmeadresse');
    }
}

