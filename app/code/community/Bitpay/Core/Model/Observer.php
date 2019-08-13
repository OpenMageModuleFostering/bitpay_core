<?php
/**
 * @license Copyright 2011-2015 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Model_Observer
{
    /*
     * TODO: Why is this here?
     */
    public function checkForRequest($observer)
    {
    }

    /*
     * Queries BitPay to update the order states in magento to make sure that
     * open orders are closed/canceled if the BitPay invoice expires or becomes
     * invalid.
     */
    public function updateOrderStates()
    {
        $apiKey = \Mage::getStoreConfig('payment/bitpay/api_key');

        if (false === isset($apiKey) || empty($apiKey)) {
            \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::updateOrderStates() could not start job to update the order states because the API key was not set.');
            return;
        } else {
            \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::updateOrderStates() started job to query BitPay to update the existing order states.');
        }

        /*
         * Get all of the orders that are open and have not received an IPN for
         * complete, expired, or invalid.
         */
        $orders = \Mage::getModel('bitpay/ipn')->getOpenOrders();

        if (false === isset($orders) || empty($orders)) {
            \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::updateOrderStates() could not retrieve the open orders.');
            return;
        } else {
            \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::updateOrderStates() successfully retrieved existing open orders.');
        }

        /*
         * Get all orders that have been paid using bitpay and
         * are not complete/closed/etc
         */
        foreach ($orders as $order) {
            /*
             * Query BitPay with the invoice ID to get the status. We must take
             * care not to anger the API limiting gods and disable our access
             * to the API.
             */
            $status = null;

            // TODO:
            // Does the order need to be updated?
            // Yes? Update Order Status
            // No? continue
        }

        \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::updateOrderStates() order status update job finished.');
    }

    /**
     * Method that is called via the magento cron to update orders if the
     * invoice has expired
     */
    public function cleanExpired()
    {
        \Mage::helper('bitpay')->debugData('[INFO] Bitpay_Core_Model_Observer::cleanExpired() called.');
        \Mage::helper('bitpay')->cleanExpired();
    }
}
