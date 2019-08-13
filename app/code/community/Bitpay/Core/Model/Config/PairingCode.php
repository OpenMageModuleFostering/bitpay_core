<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * This class will take the pairing code the merchant entered and pair it with
 * BitPay's API.
 */
class Bitpay_Core_Model_Config_PairingCode extends Mage_Core_Model_Config_Data
{
    /**
     * @inheritdoc
     */
    public function save()
    {
        /**
         * If the user has put a paring code into the text field, we want to
         * pair the magento store to the stores keys. If the merchant is just
         * updating a configuration setting, we could care less about the
         * pairing code.
         */
        $pairingCode = trim($this->getValue());

        if (empty($pairingCode)) {
            return;
        }

        Mage::helper('bitpay')->debugData('Attempting Pair Code');

        try {
            Mage::helper('bitpay')->sendPairingRequest($pairingCode);
        } catch (Exception $e) {
            Mage::helper('bitpay')->debugData(
                sprintf('Error Pairing Code "%s"', $e->getMessage())
            );
            Mage::getSingleton('core/session')->addError(
                'There was an error while trying to pair the pairing code. Please try again or enabled debug mode and send the "payment_bitpay.log" file to support.'
            );

            return;
        }

        Mage::getSingleton('core/session')->addSuccess('Pairing Code was successful.');
    }
}
