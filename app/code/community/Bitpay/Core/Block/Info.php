<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Block_Info extends Mage_Payment_Block_Info
{
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('bitpay/info/default.phtml');
    }

    public function getBitpayInvoiceUrl()
    {
        $order       = $this->getInfo()->getOrder();
        $incrementId = $order->getIncrementId();

        $bitpayInvoice = Mage::getModel('bitpay/invoice')->load($incrementId, 'increment_id');

        if ($bitpayInvoice) {
            return $bitpayInvoice->getUrl();
        }
    }
}
