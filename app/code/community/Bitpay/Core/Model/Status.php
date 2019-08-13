<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Model_Status
{
    const STATUS_NEW       = 'new';
    const STATUS_PAID      = 'paid';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETE  = 'complete';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_INVALID   = 'invalid';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::STATUS_NEW, 'label'       => Mage::helper('bitpay')->__('New')),
            array('value' => self::STATUS_PAID, 'label'      => Mage::helper('bitpay')->__('Paid')),
            array('value' => self::STATUS_CONFIRMED, 'label' => Mage::helper('bitpay')->__('Confirmed')),
            array('value' => self::STATUS_COMPLETE, 'label'  => Mage::helper('bitpay')->__('Complete')),
            array('value' => self::STATUS_EXPIRED, 'label'   => Mage::helper('bitpay')->__('Expired')),
            array('value' => self::STATUS_INVALID, 'label'   => Mage::helper('bitpay')->__('Invalid')),
        );
    }
}
