<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Bitcoins_Model_Resource_Ipn_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Bitcoins/ipn');
    }

    public function delete()
    {
        foreach ($this->getItems() as $item) {
            $item->delete();
        }
    }
}
