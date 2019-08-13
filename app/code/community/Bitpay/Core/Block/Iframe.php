<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
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
        if (Mage::getModel('bitpay/ipn')->getQuotePaid($this->getQuote()->getId())) {
            return 'paid'; // quote's already paid, so don't show the iframe
        }

        /*** @var Bitpay_Core_Model_PaymentMethod ***/
        $method  = $this->getQuote()->getPayment()->getMethodInstance();
        $options = array_merge(
            array(
                'currency'          => $this->getQuote()->getQuoteCurrencyCode(),
                'fullNotifications' => 'true',
                'notificationURL'   => Mage::getUrl('bitpay/ipn'),
                'redirectURL'       => Mage::getUrl('checkout/onepage/success'),
                'transactionSpeed'  => Mage::getStoreConfig('payment/bitpay/speed'),
            ),
            $method->extractAddress($this->getQuote()->getShippingAddress())
        );
        Mage::helper('bitpay')->debugData($options);

        // Mage doesn't round the total until saving and it can have more precision
        // at this point which would be bad for later comparing records w/ bitpay.
        // So round here to match what the price will be saved as:
        $price = round($this->getQuote()->getGrandTotal(), 4);

        //serialize info about the quote to detect changes
        $hash = $method->getQuoteHash($this->getQuote()->getId());

        Mage::helper('bitpay')->registerAutoloader();
        //$invoice = bpCreateInvoice($quoteId, $price, array('quoteId' => $quoteId, 'quoteHash' => $hash), $options);
        $invoice = array('url' => 'https://test.bitpay.com/invoice?id=5NxFkXcJbCSivtQRJa4kHP');

        if (array_key_exists('error', $invoice)) {
            Mage::helper('bitpay')->debugData(
                array(
                    'Error creating bitpay invoice',
                    $invoice['error'],
                )
            );
            Mage::throwException("Error creating BitPay invoice. Please try again or use another payment option.");

            return false;
        }

        return $invoice['url'].'&view=iframe';
    }
}
