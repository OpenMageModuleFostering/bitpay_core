<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * Bitcoin payment method support by BitPay
 */
class Bitpay_Core_Model_Method_Bitcoin extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                        = 'bitpay';
    protected $_formBlockType               = 'bitpay/form_bitpay';
    protected $_infoBlockType               = 'bitpay/info';
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = false;
    protected $_canUseInternal              = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = false;
    protected $_canManagerRecurringProfiles = false;
    //protected $_canUseCheckout            = true;
    //protected $_canUseForMultishipping    = true;
    //protected $_canCapturePartial         = false;
    //protected $_canRefund                 = false;
    //protected $_canVoid                   = false;
    protected $_debugReplacePrivateDataKeys = array();
    protected static $_redirectUrl;

    /**
     * @param  Mage_Sales_Model_Order_Payment  $payment
     * @param  float                           $amount
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $this->debugData('authorizing new order');

        // Create BitPay Invoice
        $invoice = $this->initializeInvoice();
        $invoice = $this->prepareInvoice($invoice, $payment, $amount);

        try {
            $bitpayInvoice = Mage::helper('bitpay')->getBitpayClient()->createInvoice($invoice);
        } catch (Exception $e) {
            $this->debugData($e->getMessage());
            $this->debugData(
                array(
                    Mage::helper('bitpay')->getBitpayClient()->getRequest()->getBody(),
                    Mage::helper('bitpay')->getBitpayClient()->getResponse()->getBody(),
                )
            );
            Mage::throwException('Could not authorize transaction.');
        }

        self::$_redirectUrl = $bitpayInvoice->getUrl();
        $this->debugData(
            array(
                'BitPay Invoice created',
                sprintf('Invoice URL: "%s"', $bitpayInvoice->getUrl()),
            )
        );

        // Save BitPay Invoice in database for reference
        $mirrorInvoice = Mage::getModel('bitpay/invoice')
            ->prepareWithBitpayInvoice($bitpayInvoice)
            ->prepateWithOrder($payment->getOrder())
            ->save();

        $this->debugData($bitpayInvoice->getId());

        return $this;
    }

    /**
     * This makes sure that the merchant has setup the extension correctly
     * and if they have not, it will not show up on the checkout.
     *
     * @see Mage_Payment_Model_Method_Abstract::canUseCheckout()
     * @return bool
     */
    public function canUseCheckout()
    {
        $token = Mage::getStoreConfig('payment/bitpay/token');

        if (empty($token)) {
            /**
             * Merchant must goto their account and create a pairing code to
             * enter in.
             */
            $this->debugData('Magento store does not have a BitPay token.');

            return false;
        }

        return true;
    }

    /**
     * Fetchs an invoice from BitPay
     *
     * @param string $id
     * @return Bitpay\Invoice
     */
    public function fetchInvoice($id)
    {
        Mage::helper('bitpay')->registerAutoloader();

        $client  = Mage::helper('bitpay')->getBitpayClient();
        $invoice = $client->getInvoice($id);

        return $invoice;
    }

    /**
     * given Mage_Core_Model_Abstract, return api-friendly address
     *
     * @param $address
     *
     * @return array
     */
    public function extractAddress($address)
    {
        $this->debugData(
            sprintf('Extracting addess')
        );

        $options              = array();
        $options['buyerName'] = $address->getName();

        if ($address->getCompany()) {
            $options['buyerName'] = $options['buyerName'].' c/o '.$address->getCompany();
        }

        $options['buyerAddress1'] = $address->getStreet1();
        $options['buyerAddress2'] = $address->getStreet2();
        $options['buyerAddress3'] = $address->getStreet3();
        $options['buyerAddress4'] = $address->getStreet4();
        $options['buyerCity']     = $address->getCity();
        $options['buyerState']    = $address->getRegionCode();
        $options['buyerZip']      = $address->getPostcode();
        $options['buyerCountry']  = $address->getCountry();
        $options['buyerEmail']    = $address->getEmail();
        $options['buyerPhone']    = $address->getTelephone();

        // trim to fit API specs
        foreach (array('buyerName', 'buyerAddress1', 'buyerAddress2', 'buyerAddress3', 'buyerAddress4', 'buyerCity', 'buyerState', 'buyerZip', 'buyerCountry', 'buyerEmail', 'buyerPhone') as $f) {
            $options[$f] = substr($options[$f], 0, 100);
        }

        return $options;
    }

    /**
     * This is called when a user clicks the `Place Order` button
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $this->debugData(
            'Customer wants to place the order. Create invoice and redirect user to invoice'
        );

        return self::$_redirectUrl;
    }

    /**
     * Create a new invoice with as much info already added. It should add
     * some basic info and setup the invoice object.
     *
     * @return Bitpay\Invoice
     */
    private function initializeInvoice()
    {
        Mage::helper('bitpay')->registerAutoloader();

        $invoice = new Bitpay\Invoice();
        $invoice->setFullNotifications(true);
        $invoice->setTransactionSpeed(Mage::getStoreConfig('payment/bitpay/speed'));
        $invoice->setNotificationUrl(Mage::getUrl(Mage::getStoreConfig('payment/bitpay/notification_url')));
        $invoice->setRedirectUrl(Mage::getUrl(Mage::getStoreConfig('payment/bitpay/redirect_url')));

        return $invoice;
    }

    /**
     * Prepares the invoice object to be sent to BitPay's API. This method sets
     * all the other info that we have to rely on other objects for.
     *
     * @param Bitpay\Invoice                  $invoice
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @param  float                          $amount
     * @return Bitpay\Invoice
     */
    private function prepareInvoice($invoice, $payment, $amount)
    {
        $invoice->setOrderId($payment->getOrder()->getIncrementId());
        $invoice->setPosData(
            json_encode(
                array(
                    'id' => $payment->getOrder()->getIncrementId(),
                )
            )
        );

        $invoice = $this->addCurrencyInfo($invoice, $payment->getOrder());
        $invoice = $this->addPriceInfo($invoice, $amount);
        $invoice = $this->addBuyerInfo($invoice, $payment->getOrder());

        return $invoice;
    }

    /**
     * This adds the buyer information to the invoice.
     *
     * @param Bitpay\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Bitpay\Invoice
     */
    private function addBuyerInfo($invoice, $order)
    {
        $buyer = new Bitpay\Buyer();
        $buyer->setFirstName($order->getCustomerFirstname());
        $buyer->setLastName($order->getCustomerLastname());
        $invoice->setBuyer($buyer);

        return $invoice;
    }

    /**
     * Adds currency information to the invoice
     *
     * @param Bitpay\Invoice         $invoice
     * @param Mage_Sales_Model_Order $order
     * @return Bitpay\Invoice
     */
    private function addCurrencyInfo($invoice, $order)
    {
        $currency = new Bitpay\Currency();
        $currency->setCode($order->getBaseCurrencyCode());
        $invoice->setCurrency($currency);

        return $invoice;
    }

    /**
     * Adds pricing information to the invoice
     *
     * @param Bitpay\Invoice  invoice
     * @param float           $amount
     * @return Bitpay\Invoice
     */
    private function addPriceInfo($invoice, $amount)
    {
        $item = new \Bitpay\Item();
        $item->setPrice($amount);
        $invoice->setItem($item);

        return $invoice;
    }
}
