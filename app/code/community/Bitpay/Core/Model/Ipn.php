<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 */
class Bitpay_Core_Model_Ipn extends Mage_Core_Model_Abstract
{
    /**
     */
    protected function _construct()
    {
        $this->_init('bitpay/ipn');
    }

    /**
     * @param $invoice
     * @return Varien_Object
     */
    public function record($invoice)
    {
        return $this
            ->setQuoteId(isset($invoice['posData']['quoteId']) ? $invoice['posData']['quoteId'] : null)
            ->setOrderId(isset($invoice['posData']['orderId']) ? $invoice['posData']['orderId'] : null)
            ->setPosData(json_encode($invoice['posData']))
            ->setInvoiceId($invoice['id'])
            ->setUrl($invoice['url'])
            ->setStatus($invoice['status'])
            ->setBtcPrice($invoice['btcPrice'])
            ->setPrice($invoice['price'])
            ->setCurrency($invoice['currency'])
            ->setInvoiceTime(intval($invoice['invoiceTime']/1000.0))
            ->setExpirationTime(intval($invoice['expirationTime']/1000.0))
            ->setCurrentTime(intval($invoice['currentTime']/1000.0))
            ->save();
    }

    /**
     * @param  string  $quoteId
     * @param  array   $statuses
     * @return boolean
     */
    public function getStatusReceived($quoteId, $statuses)
    {
        if (!$quoteId) {
            return false;
        }

        $quote = Mage::getModel('sales/quote')->load($quoteId, 'entity_id');

        if (!$quote) {
            Mage::log('quote not found', Zend_Log::WARN, 'bitpay.log');

            return false;
        }

        $quoteHash = Mage::getModel('bitpay/paymentMethod')->getQuoteHash($quoteId);

        if (!$quoteHash) {
            Mage::log('Could not find quote hash for quote '.$quoteId, Zend_Log::WARN, 'bitpay.log');

            return false;
        }

        $collection = $this->getCollection()->AddFilter('quote_id', $quoteId);

        foreach ($collection as $i) {
            if (in_array($i->getStatus(), $statuses)) {
                // check that quote data was not updated after IPN sent
                $posData = json_decode($i->getPosData());

                if (!$posData) {
                    continue;
                }

                if ($quoteHash == $posData->quoteHash) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  string  $quoteId
     * @return boolean
     */
    public function getQuotePaid($quoteId)
    {
        return $this->getStatusReceived($quoteId, array('paid', 'confirmed', 'complete'));
    }

    /**
     * @param string $quoteId
     *
     * @return boolean
     */
    public function getQuoteComplete($quoteId)
    {
        return $this->getStatusReceived($quoteId, array('confirmed', 'complete'));
    }

    /**
     * This method returns an array of orders in the database that have paid
     * using bitcoins, but are still open and we need to query the invoice
     * IDs at BitPay and see if they invoice has expired, is invalid, or is
     * complete.
     *
     * @return array
     */
    public function getOpenOrders()
    {
        $doneCollection = $this->getCollection();

        /**
         * Get all the IPNs that have been completed
         *
         * SELECT
         *   order_id
         * FROM
         *   bitpay_ipns
         * WHERE
         *   status IN ('completed','invalid','expired') AND order_id IS NOT NULL
         * GROUP BY
         *   order_id
         */
        $doneCollection
            ->addFieldToSelect('order_id')
            ->addFieldToFilter(
                'status',
                array(
                    'in' => array(
                        'complete',
                        'invalid',
                        'expired',
                    ),
                )
            );
        $doneCollection
            ->getSelect()
            ->where('order_id IS NOT NULL')
            ->group('order_id');

        $collection = $this->getCollection();

        /**
         * Get all the open orders that have not received a IPN that closes
         * the invoice.
         *
         * SELECT
         *   *
         * FROM
         *   bitpay_ipns
         * JOIN
         *   'sales/order' ON bitpay_ipns.order_id='sales/order'.increment_id
         * WHERE
         *   order_id NOT IN (?) AND order_id IS NOT NULL
         * GROUP BY
         *   order_id
         */
        if (0 < $doneCollection->count()) {
            $collection
                ->addFieldToFilter(
                    'status',
                    array(
                        'in' => $doneCollection->getColumnValues('order_id'),
                    )
                );
        }

        $collection
            ->getSelect()
            ->where('order_id IS NOT NULL')
            ->group('order_id');

        return $collection->getItems();
    }

    /**
     * Returns all records that have expired
     */
    public function getExpired()
    {
        $collection = $this->getCollection();
        $now        = new DateTime('now', new DateTimezone('UTC'));

        $collection
            ->removeFieldFromSelect('status')
            ->addFieldToFilter(
                'expiration_time',
                array(
                    'lteq' => $now->getTimestamp(),
                )
            );

        $collection
            ->getSelect()
            ->group('order_id')
            // Newest to oldest
            ->order('expiration_time DESC');

        return $collection->getItems();
    }

    /**
     * This will delete all records that match the order id (order id is also
     * the increment id of the magento order)
     *
     * @see Bitpay_Core_Model_Resource_Ipn_Collection::delete()
     *
     * @param string $orderId
     */
    public function deleteByOrderId($orderId)
    {
        $collection = Mage::getModel('Bitcoins/ipn')
            ->getCollection();
        $collection
            ->getSelect()
            ->where('order_id = ?', $orderId);

        $collection->delete();
    }
}
