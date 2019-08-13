<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

namespace Bitpay\Storage;

/**
 * This is part of the magento plugin. This is responsible for saving and loading
 * keys for magento.
 */
class MagentoStorage implements StorageInterface
{
    /**
     * @var array
     */
    protected $_keys;

    /**
     * @inheritdoc
     */
    public function persist(\Bitpay\KeyInterface $key)
    {
        $this->_keys[$key->getId()] = $key;

        $data          = serialize($key);
        $encryptedData = \Mage::helper('core')->encrypt($data);
        $config        = new \Mage_Core_Model_Config();
        $config->saveConfig($key->getId(), $encryptedData);
    }

    /**
     * @inheritdoc
     */
    public function load($id)
    {
        if (isset($this->_keys[$id])) {
            return $this->_keys[$id];
        }

        $entity = \Mage::getStoreConfig($id);

        /**
         * Not in database
         */
        if (empty($entity)) {
            return false;
        }

        $decodedEntity = unserialize(\Mage::helper('core')->decrypt($entity));

        if (empty($decodedEntity)) {
            return false;
        }

        return $decodedEntity;
    }
}
