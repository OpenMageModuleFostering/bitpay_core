<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 */
class Bitpay_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * File that is used to put all logging information in.
     *
     * @var string
     */
    const LOG_FILE = 'payment_bitpay.log';

    protected $_autoloaderRegistered;
    protected $_bitpay;
    protected $_sin;
    protected $_publicKey;
    protected $_privateKey;
    protected $_keyManager;
    protected $_client;

    /**
     * Returns the file used for logging
     *
     * @return string
     */
    public function getLogFile()
    {
        return self::LOG_FILE;
    }

    /**
     * @param mixed $debugData
     */
    public function debugData($debugData)
    {
        Mage::getModel('bitpay/paymentMethod')->debugData($debugData);
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return (boolean) Mage::getStoreConfig('payment/bitpay/debug');
    }

    /**
     * Returns true if Transaction Speed has been configured
     *
     * @return boolean
     */
    public function hasTransactionSpeed()
    {
        $speed = Mage::getStoreConfig('payment/bitpay/speed');

        return !empty($speed);
    }

    /**
     * This method is used to removed IPN records in the database that
     * are expired and update the magento orders to canceled if they have
     * expired.
     */
    public function cleanExpired()
    {
        $expiredRecords = Mage::getModel('bitpay/ipn')->getExpired();

        foreach ($expiredRecords as $ipn) {
            $incrementId = $ipn->getOrderId();
            if (empty($incrementId)) {
                $this->logIpnParseError($ipn);
                continue;
            }

            // Cancel the order
            $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
            $this->cancelOrder($order);

            // Delete all IPN records for order id
            Mage::getModel('bitpay/ipn')
                ->deleteByOrderId($ipn->getOrderId());
            Mage::log(
                sprintf('Deleted Record: %s', $ipn->toJson()),
                Zend_Log::DEBUG,
                self::LOG_FILE
            );
        }
    }

    /**
     * Log error if there is an issue parsing an IPN record
     *
     * @param Bitpay_Core_Model_Ipn $ipn
     * @param boolean               $andDelete
     */
    private function logIpnParseError(Bitpay_Core_Model_Ipn $ipn, $andDelete = true)
    {
        Mage::log(
            'Error processing IPN record',
            Zend_Log::DEBUG,
            self::LOG_FILE
        );
        Mage::log(
            $ipn->toJson(),
            Zend_Log::DEBUG,
            self::LOG_FILE
        );

        if ($andDelete) {
            $ipn->delete();
            Mage::log(
                'IPN record deleted from database',
                Zend_Log::DEBUG,
                self::LOG_FILE
            );
        }
    }

    /**
     * This will cancel the order in the magento database, this will return
     * true if the order was canceled or it will return false if the order
     * was not updated. For example, if the order is complete, we don't want
     * to cancel that order so this method would return false.
     *
     * @param Mage_Sales_Model_Order
     *
     * @return boolean
     */
    private function cancelOrder(Mage_Sales_Model_Order $order)
    {
        $orderState = $order->getState();

        /**
         * These order states are useless and can just be skipped over. No
         * need to cancel an order that is alread canceled.
         */
        $statesWeDontCareAbout = array(
            Mage_Sales_Model_Order::STATE_CANCELED,
            Mage_Sales_Model_Order::STATE_CLOSED,
            Mage_Sales_Model_Order::STATE_COMPLETE,
        );

        if (in_array($orderState, $statesWeDontCareAbout)) {
            return false;
        }

        $order->setState(
            Mage_Sales_Model_Order::STATE_CANCELED,
            true,
            'BitPay Invoice has expired', // Comment
            false // notifiy customer?
        )->save();
        Mage::log(
            sprintf('Order "%s" has been canceled', $order->getIncrementId()),
            Zend_Log::DEBUG,
            self::LOG_FILE
        );

        return true;
    }

    /**
     * @param Varien_Object $payment
     *
     * @return Bitpay_Core_Model_PaymentMethod
     */
    public function checkForPayment(Varien_Object $payment)
    {
        Mage::log(
            sprintf('Checking for payment'),
            Zend_Log::DEBUG,
            Mage::helper('bitpay')->getLogFile()
        );

        $quoteId = $payment->getOrder()->getQuoteId();
        $ipn     = Mage::getModel('bitpay/ipn');

        if (!$ipn->GetQuotePaid($quoteId)) {
            // This is the error that is displayed to the customer during checkout.
            Mage::throwException("Order not paid for.  Please pay first and then Place your Order.");
            Mage::log('Order not paid for. Please pay first and then Place Your Order.', Zend_Log::CRIT, Mage::helper('bitpay')->getLogFile());
        } elseif (!$ipn->GetQuoteComplete($quoteId)) {
            // order status will be PAYMENT_REVIEW instead of PROCESSING
            $payment->setIsTransactionPending(true);
        } else {
            $this->MarkOrderPaid($payment->getOrder());
        }

        return $this;
    }

    /**
     * Returns the URL where the IPN's are sent
     *
     * @return string
     */
    public function getNotificationUrl()
    {
        return Mage::getUrl(Mage::getStoreConfig('payment/bitpay/notification_url'));
    }

    /**
     * Returns the URL where customers are redirected
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return Mage::getUrl(Mage::getStoreConfig('payment/bitpay/redirect_url'));
    }

    /**
     * Registers the BitPay autoloader to run before Magento's. This MUST be
     * called before using any bitpay classes.
     */
    public function registerAutoloader()
    {
        if (null === $this->_autoloaderRegistered) {
            require_once Mage::getBaseDir('lib').'/Bitpay/Autoloader.php';
            \Bitpay\Autoloader::register();
            $this->_autoloaderRegistered = true;
            $this->debugData('BitPay Autoloader has been registered');
        }
    }

    /**
     * This function will generate keys that will need to be paired with BitPay
     * using
     */
    public function generateAndSaveKeys()
    {
        $this->debugData('Generating Keys');
        $this->registerAutoloader();

        $this->_privateKey = new Bitpay\PrivateKey('payment/bitpay/private_key');
        $this->_privateKey->generate();

        $this->_publicKey = new Bitpay\PublicKey('payment/bitpay/public_key');
        $this->_publicKey
            ->setPrivateKey($this->_privateKey)
            ->generate();

        $this->getKeyManager()->persist($this->_publicKey);
        $this->getKeyManager()->persist($this->_privateKey);

        $this->debugData('Keys persisted to database');
    }

    /**
     * Send a pairing request to BitPay to receive a Token
     */
    public function sendPairingRequest($pairingCode)
    {
        $this->debugData(
            sprintf('Sending Paring Request with pairing code "%s"', $pairingCode)
        );

        $sin = $this->getSinKey();

        $this->debugData(
            sprintf('Sending Pairing Request for SIN "%s"', (string) $sin)
        );

        $token = $this->getBitpayClient()->createToken(
            array(
                'id'          => (string) $sin,
                'pairingCode' => $pairingCode,
                'label'       => sprintf('[Magento Store] %s', Mage::app()->getStore()->getName()),
            )
        );

        $this->debugData('Token Obtained');

        $config = new \Mage_Core_Model_Config();
        $config->saveConfig('payment/bitpay/token', $token->getToken());

        $this->debugData('Token Persisted persisted to database');
    }

    /**
     * @return Bitpay\SinKey
     */
    public function getSinKey()
    {
        if (null !== $this->_sin) {
            return $this->_sin;
        }

        $this->debugData('Getting SIN Key');

        $this->registerAutoloader();
        $this->_sin = new Bitpay\SinKey();
        $this->_sin
            ->setPublicKey($this->getPublicKey())
            ->generate();

        return $this->_sin;
    }

    public function getPublicKey()
    {
        if (null !== $this->_publicKey) {
            return $this->_publicKey;
        }

        $this->debugData('Getting Public Key');

        $this->_publicKey = $this->getKeyManager()->load('payment/bitpay/public_key');

        if (!$this->_publicKey) {
            $this->generateAndSaveKeys();
        }

        return $this->_publicKey;
    }

    public function getPrivateKey()
    {
        if (null !== $this->_privateKey) {
            return $this->_privateKey;
        }

        $this->debugData('Getting Private Key');

        $this->_privateKey = $this->getKeyManager()->load('payment/bitpay/private_key');

        if (!$this->_publicKey) {
            $this->generateAndSaveKeys();
        }

        return $this->_privateKey;
    }

    /**
     * @return Bitpay\KeyManager
     */
    public function getKeyManager()
    {
        if (null == $this->_keyManager) {
            $this->registerAutoloader();
            $this->debugData('Creating instance of KeyManager');
            $this->_keyManager = new Bitpay\KeyManager(new Bitpay\Storage\MagentoStorage());
        }

        return $this->_keyManager;
    }

    /**
     * Initialize an instance of Bitpay or return the one that has already
     * been created.
     *
     * @return Bitpay\Bitpay
     */
    public function getBitpay()
    {
        if (null === $this->_bitpay) {
            $this->registerAutoloader();
            $this->_bitpay = new Bitpay\Bitpay(array('bitpay' => $this->getBitpayConfig()));
        }

        return $this->_bitpay;
    }

    /**
     * Sets up the bitpay container with settings for magento
     *
     * @return array
     */
    protected function getBitpayConfig()
    {
        return array(
            'public_key'  => 'payment/bitpay/public_key',
            'private_key' => 'payment/bitpay/private_key',
            'network'     => Mage::getStoreConfig('payment/bitpay/network'),
            'key_storage' => '\\Bitpay\\Storage\\MagentoStorage',
        );
    }

    /**
     * @return Bitpay\Client
     */
    public function getBitpayClient()
    {
        if (null !== $this->_client) {
            return $this->_client;
        }

        $this->registerAutoloader();

        $this->_client = new Bitpay\Client\Client();
        $this->_client->setPublicKey($this->getPublicKey());
        $this->_client->setPrivateKey($this->getPrivateKey());
        $this->_client->setNetwork($this->getBitpay()->get('network'));
        $this->_client->setAdapter($this->getBitpay()->get('adapter'));
        $this->_client->setToken($this->getToken());

        return $this->_client;
    }

    public function getToken()
    {
        $this->registerAutoloader();
        $token = new Bitpay\Token();
        $token->setToken(Mage::getStoreConfig('payment/bitpay/token'));

        return $token;
    }
}
