<?php
/**
 * @license Copyright 2011-2015 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 */
class Bitpay_Core_Model_Ipn extends Mage_Core_Model_Abstract
{
    /**
     */
    protected function _construct()
    {
    	parent::_construct();
        $this->_init('bitpay/ipn');
    }

    /**
     * @param string $quoteId
     * @param array  $statuses
     *
     * @return boolean
     */
    function GetStatusReceived($quoteId, $statuses)
    {
        if (!$quoteId)
        {
            return false;
        }

        $quote = Mage::getModel('sales/quote')->load($quoteId, 'entity_id');

        if (!$quote)
        {
        	Mage::log('quote not found', Zend_Log::WARN, 'bitpay.log');
            return false;
        }

        $collection = $this->getCollection();

        foreach ($collection as $i)
        {
            if ($quoteId == json_decode($i->pos_data, true)['quoteId']) {
                if (in_array($i->status, $statuses)) {
                    return true;
                }
            }
        }

        return false;		
    }

    /**
     * @param string $quoteId
     *
     * @return boolean
     */
    function GetQuotePaid($quoteId)
    {
        return $this->GetStatusReceived($quoteId, array('paid', 'confirmed', 'complete'));
    }

}
