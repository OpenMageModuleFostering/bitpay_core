<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 */
class Bitpay_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_autoloaderRegistered;
    protected $_bitpay;
    protected $_sin;
    protected $_publicKey;
    protected $_privateKey;
    protected $_keyManager;
    protected $_client;

    /**
     * @param mixed $debugData
     */
    public function debugData($debugData)
    {
        Mage::getModel('bitpay/method_bitcoin')->debugData($debugData);
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

        // Generate/Regenerate keys
        $this->generateAndSaveKeys();
        $sin = $this->getSinKey();

        $this->debugData(
            sprintf('Sending Pairing Request for SIN "%s"', (string) $sin)
        );

        // Sanitize label
        $label = preg_replace('/[^a-zA-Z0-9 \-\_\.]/', '', Mage::app()->getStore()->getName());
        $label = substr('Magento - '.$label, 0, 59);

        $token = $this->getBitpayClient()->createToken(
            array(
                'id'          => (string) $sin,
                'pairingCode' => $pairingCode,
                'label'       => $label,
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
