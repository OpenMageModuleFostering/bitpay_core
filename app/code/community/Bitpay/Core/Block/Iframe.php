<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 * 
 * TODO: Finish this iFrame implemenation... :/
 */

class Bitpay_Core_Block_Iframe extends Mage_Checkout_Block_Onepage_Payment
{
    /**
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('bitpay/iframe.phtml');
    }

    /**
     * create an invoice and return the url so that iframe.phtml can display it
     *
     * @return string
     */
    public function getIframeUrl()
    {

        if (!($quote = Mage::getSingleton('checkout/session')->getQuote()) 
            or !($payment = $quote->getPayment())
            or !($paymentMethod = $payment->getMethod())
            or ($paymentMethod !== 'bitpay')
            or (Mage::getStoreConfig('payment/bitpay/fullscreen')))
        {
            return 'notbitpay';
        }

        \Mage::helper('bitpay')->registerAutoloader();

        // fullscreen disabled?
        if (Mage::getStoreConfig('payment/bitpay/fullscreen'))
        {
            return 'disabled';
        }

        if (\Mage::getModel('bitpay/ipn')->getQuotePaid($this->getQuote()->getId())) {
            return 'paid'; // quote's already paid, so don't show the iframe
        }

        /*** @var Bitpay_Core_Model_PaymentMethod ***/
        $method  = $this->getQuote()->getPayment()->getMethodInstance();

        $amount = $this->getQuote()->getGrandTotal();

        if (false === isset($method) || true === empty($method)) {
            \Mage::helper('bitpay')->debugData('[ERROR] In Bitpay_Core_Block_Iframe::getIframeUrl(): Could not obtain an instance of the payment method.');
            throw new \Exception('In Bitpay_Core_Block_Iframe::getIframeUrl(): Could not obtain an instance of the payment method.');
        }

        $bitcoinMethod = \Mage::getModel('bitpay/method_bitcoin');

        try {
            $bitcoinMethod->authorize($payment, $amount, true);
        } catch (\Exception $e) {
            \Mage::helper('bitpay')->debugData('[ERROR] In Bitpay_Core_Block_Iframe::getIframeUrl(): failed with the message: ' . $e->getMessage());
            \Mage::throwException("Error creating BitPay invoice. Please try again or use another payment option.");
            return false;
        }

        return $bitcoinMethod->getOrderPlaceRedirectUrl();
    }
}
