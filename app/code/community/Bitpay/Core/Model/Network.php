<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * Used to display bitcoin networks
 */
class Bitpay_Core_Model_Network
{
    const NETWORK_LIVENET = 'livenet';
    const NETWORK_TESTNET = 'testnet';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::NETWORK_LIVENET, 'label' => Mage::helper('bitpay')->__('Livenet')),
            array('value' => self::NETWORK_TESTNET, 'label' => Mage::helper('bitpay')->__('Testnet')),
        );
    }
}
