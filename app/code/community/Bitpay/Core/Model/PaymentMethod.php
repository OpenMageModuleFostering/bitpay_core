<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                        = 'bitpay';
    protected $_formBlockType               = 'bitpay/form_bitpay';
    //protected $_infoBlockType               = 'bitpay/info';
    protected $_isGateway                   = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = false;
    protected $_canUseInternal              = false;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canManagerRecurringProfiles = false;
    //protected $_canUseCheckout            = true;
    //protected $_canUseForMultishipping    = true;
    //protected $_canCapturePartial         = false;
    //protected $_canRefund                 = false;
    //protected $_canVoid                   = false;
    protected $_debugReplacePrivateDataKeys = array();
    protected static $_redirectUrl;

    /**
     * @param  Varien_Object                   $payment
     * @param  float                           $amount
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        Mage::helper('bitpay')->registerAutoloader();

        $order = $payment->getOrder();
        $this->debugData(
            array(
                'authorize',
                get_class($payment), // Mage_Sales_Model_Order_Payment
                $amount,
                get_class($order),
            )
        );

        // Create BitPay Invoice
        $invoice = new Bitpay\Invoice();
        $invoice->setOrderId($order->getIncrementId());
        $invoice->setFullNotifications(true);
        $invoice->setTransactionSpeed(Mage::getStoreConfig('payment/bitpay/speed'));
        $invoice->setPosData(
            json_encode(
                array(
                    'id' => $order->getIncrementId(),
                )
            )
        );

        $buyer = new Bitpay\Buyer();
        $buyer->setFirstName($order->getCustomerFirstname());
        $buyer->setLastName($order->getCustomerLastname());
        $invoice->setBuyer($buyer);

        $currency = new Bitpay\Currency();
        $currency->setCode($order->getBaseCurrencyCode());
        $invoice->setCurrency($currency);

        $item = new \Bitpay\Item();
        $item->setPrice($amount);
        $invoice->setItem($item);

        $invoice->setNotificationUrl(
            Mage::getUrl(
                Mage::getStoreConfig('payment/bitpay/notification_url')
            )
        );

        $invoice->setRedirectUrl(
            Mage::getUrl(
                Mage::getStoreConfig('payment/bitpay/redirect_url')
            )
        );


        try {
            $bitpayInvoice = Mage::helper('bitpay')->getBitpayClient()->createInvoice($invoice);
            self::$_redirectUrl = $bitpayInvoice->getUrl();
            $this->debugData(
                array(
                    'BitPay Invoice created',
                    sprintf('Invoice URL: "%s"', $bitpayInvoice->getUrl()),
                )
            );
        } catch (Exception $e) {
            $this->debugData($e->getMessage());
            $this->debugData(
                array(
                    Mage::helper('bitpay')->getBitpayClient()->getRequest()->getBody(),
                    Mage::helper('bitpay')->getBitpayClient()->getResponse()->getBody(),
                )
            );
            Mage::throwException('Could not authorize transaction.');
            return;
        }

        // Save BitPay Invoice in database
        $mirrorInvoice = Mage::getModel('bitpay/invoice');
        //$mirrorInvoice->setData(
        //    array(
        //    )
        //);
        $mirrorInvoice->save();


        return $this;
    }

    /**
     * @param  Varien_Object                   $payment
     * @param  float                           $amount
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function cancel(Varien_Object $payment)
    {
        $this->debugData(
            array(
                'cancel',
                $payment,
            )
        );
    }

    /**
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $this->debugData(
            array(
                'capture',
                $payment,
                $amount,
            )
        );
    }

    /**
     * Order payment abstract method
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function order(Varien_Object $payment, $amount)
    {
        $this->debugData(
            array(
                'order',
                $payment,
                $amount,
            )
        );
    }

    /**
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $this->debugData(
            array(
                'refund',
                $payment,
                $amount,
            )
        );
    }

    /**
     */
    public function void(Varien_Object $payment)
    {
        $this->debugData(
            array(
                'void',
                $payment,
                $amount,
            )
        );
    }

    /**
     * Check method for processing with base currency
     * @see Mage_Payment_Model_Method_Abstract::canUseForCurrency()
     *
     * @param  string  $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        return parent::canUseForCurrency($currencyCode);
        //Mage::log(
        //    sprintf('Checking if can use currency "%s"', $currencyCode),
        //    Zend_Log::DEBUG,
        //    Mage::helper('bitpay')->getLogFile()
        //);

        //$currencies = Mage::getStoreConfig('payment/bitpay/currencies');
        //$currencies = array_map('trim', explode(',', $currencies));

        //return array_search($currencyCode, $currencies) !== false;
    }

    /**
     * Can be used in regular checkout
     * @see Mage_Payment_Model_Method_Abstract::canUseCheckout()
     *
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
            $this->debugData(
                'Magento store does not have a BitPay token.'
            );

            return false;
        }

        return $this->_canUseCheckout;
    }

    /**
     * @param Varien_Object $order
     */
    public function invoiceOrder($order)
    {
        $this->debugData(
            array(
                'invoiceOrder',
                $order,
            )
        );

        try {
            if (!$order->canInvoice()) {
                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
            }

            $invoice = $order->prepareInvoice()
                ->setTransactionId(1)
                ->addComment('Invoiced automatically by Bitpay/Core/controllers/IndexController.php')
                ->register()
                ->pay();

            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::EMERG, Mage::helper('bitpay')->getLogFile());
            Mage::logException($e);
        }
    }

    /**
     * @param $order
     */
    public function markOrderPaid($order)
    {
        $this->debugData(
            array(
                sprintf('Marking order paid'),
                $order
            )
        );

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();

        if ($order->getTotalDue() > 0) {
            if (!count($order->getInvoiceCollection())) {
                $this->invoiceOrder($order);
            }
        } else {
            Mage::log('MarkOrderPaid called but order '.$order->getId().' does not have a balance due.', Zend_Log::WARN, Mage::helper('bitpay')->getLogFile());
        }
    }

    /**
     * @param $order
     */
    public function markOrderComplete($order)
    {
        $this->debugData(
            array(
                sprintf('Marking order paid'),
                $order,
            )
        );

        if ($order->getTotalDue() >= 0 && $order->canInvoice()) {
            if ($order->hasInvoices()) {
                foreach ($order->getInvoiceCollection() as $_eachInvoice) {
                    try {
                        $_eachInvoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                        $_eachInvoice->capture()->save();
                    } catch (Exception $e) {
                        Mage::log($e->getMessage(), Zend_Log::EMERG, Mage::helper('bitpay')->getLogFile());
                        Mage::logException($e);
                    }
                }
            }
        }

        // If the $_bpCreateShipment option is set to true above, this code will
        // programmatically create a shipment for you.  By design, this will mark
        // the entire order as 'complete'.
        if (isset($_bpCreateShipment) && $_bpCreateShipment == true) {
            try {
                $shipment = $order->prepareShipment();
                if ($shipment) {
                    $shipment->register();
                    $order->setIsInProcess(true);
                    $transaction_save = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder())
                        ->save();
                }
            } catch (Exception $e) {
                Mage::log('Error creating shipment for order '.$order->getId().'.', Zend_Log::ERR, Mage::helper('bitpay')->getLogFile());
                Mage::logException($e);
            }
        }

        try {
            if ((isset($_bpCreateShipment) && $_bpCreateShipment == true) || Mage::getStoreConfig('payment/bitpay/order_disposition')) {
                $order->setState('Complete', 'complete', 'Completed by BitPay payments.', true);
            } else {
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing', 'BitPay has confirmed the payment.', false);
            }

            if (!$order->getEmailSent()) {
                $order->sendNewOrderEmail();
            }

            $order->save();
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::EMERG, Mage::helper('bitpay')->getLogFile());
            Mage::logException($e);
        }
    }

    /**
     * @param $order
     */
    public function MarkOrderCancelled($order)
    {
        $this->debugData(
            array(
                sprintf('Marking order cancelled'),
                $order,
            )
        );

        try {
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
        } catch (Exception $e) {
            Mage::log('Could not cancel order '.$order->getId().'.', null, Mage::helper('bitpay')->getLogFile());
            Mage::logException($e);
        }
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
     * This will create a new invoice based on the payment object
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function createInvoiceAndRedirect(Varien_Object $payment, $amount)
    {
        $this->debugData(
            array(
                sprintf('Creating invoice and redirecting'),
                get_class($payment),
                $amount,
            )
        );

        Mage::helper('bitpay')->registerAutoloader();

        $apiKey  = Mage::getStoreConfig('payment/bitpay/api_key');
        $speed   = Mage::getStoreConfig('payment/bitpay/speed');
        $order   = $payment->getOrder();
        $orderId = $order->getIncrementId();
        $options = array(
            'currency'          => $order->getBaseCurrencyCode(),
            'buyerName'         => $order->getCustomerFirstname().' '.$order->getCustomerLastname(),
            'fullNotifications' => 'true',
            'notificationURL'   => Mage::getUrl('bitpay/ipn'),
            'redirectURL'       => Mage::getUrl('checkout/onepage/success'),
            'transactionSpeed'  => $speed,
            'apiKey'            => $apiKey,
        );

        /**
         * Some merchants are using custom extensions where the shipping
         * address may not be set, this will only extract the shipping
         * address if there is one already set.
         */
        if ($order->getShippingAddress()) {
            $options = array_merge(
                $options,
                $this->extractAddress($order->getShippingAddress())
            );
        }
        //$invoice  = bpCreateInvoice($orderId, $amount, array('orderId' => $orderId), $options);
        $payment->setIsTransactionPending(true); // status will be PAYMENT_REVIEW instead of PROCESSING

        if (array_key_exists('error', $invoice)) {
            Mage::log('Error creating bitpay invoice', Zend_Log::CRIT, Mage::helper('bitpay')->getLogFile());
            Mage::log($invoice['error'], Zend_Log::CRIT, Mage::helper('bitpay')->getLogFile());
            Mage::throwException("Error creating BitPay invoice.  Please try again or use another payment option.");
        } else {
            $invoiceId = Mage::getModel('sales/order_invoice_api')->create($orderId, array());
            Mage::getSingleton('customer/session')->setRedirectUrl($invoice['url']);
        }

        return $this;
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
     * computes a unique hash determined by the contents of the cart
     *
     * @param string $quoteId
     *
     * @return boolean|string
     */
    public function getQuoteHash($quoteId)
    {
        return false;
        Mage::log(
            sprintf('Getting the quote hash'),
            Zend_Log::DEBUG,
            Mage::helper('bitpay')->getLogFile()
        );

        $quote = Mage::getModel('sales/quote')->load($quoteId, 'entity_id');
        if (!$quote) {
            Mage::log('getQuoteTimestamp: quote not found', Zend_Log::ERR, Mage::helper('bitpay')->getLogFile());

            return false;
        }

        // encode items
        $items       = $quote->getAllItems();
        $latest      = null;
        $description = '';

        foreach ($items as $i) {
            $description .= 'i'.$i->getItemId().'q'.$i->getQty();
            // could encode $i->getOptions() here but item ids are incremented if options are changed
        }

        $hash = base64_encode(hash_hmac('sha256', $description, $quoteId));
        $hash = substr($hash, 0, 30); // fit it in posData maxlen

        return $hash;
    }
}
