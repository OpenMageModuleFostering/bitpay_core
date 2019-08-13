<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

class Bitpay_Core_Model_Observer
{
    /**
     */
    public function checkForRequest($observer)
    {
    }

    /**
     * Queries BitPay to update the order states in magento to make sure that
     * open orders are closed/canceled if the BitPay invoice expires or becomes
     * invalid.
     */
    public function updateOrderStates()
    {
        Mage::helper('bitpay')->debugData(
            'cronjob: started'
        );

        $apiKey = Mage::getStoreConfig('payment/bitpay/api_key');

        if (empty($apiKey)) {
            Mage::helper('bitpay')->debugData(
                'cronjob: Api Key not set.'
            );

            return; // Api Key needs to be set
        }

        /**
         * Get all of the orders that are open and have not received an IPN for
         * complete, expired, or invalid.
         *
         * If anyone knows of a better way to do this, please let me know
         */
        $orders = Mage::getModel('bitpay/ipn')->getOpenOrders();

        /**
         * Get all orders that have been paid using bitpay and
         * are not complete/closed/etc
         */
        foreach ($orders as $order) {
            /**
             * Query BitPay with the invoice ID to get the status. We must take
             * care not to anger the API limiting gods and disable our access
             * to the API.
             */
            $status = null;

            // Does the order need to be updated?
            // Yes? Update Order Status
            // No? continue
        }

        Mage::helper('bitpay')->debugData(
            'cronjob: end'
        );
    }

    /**
     * Method that is called via the magento cron to update orders if the
     * invoice has expired
     */
    public function cleanExpired()
    {
        Mage::helper('bitpay')->cleanExpired();
    }
}
