<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 */
class Bitpay_Core_Model_Invoice extends Mage_Core_Model_Abstract
{
    /**
     */
    protected function _construct()
    {
        $this->_init('bitpay/invoice');
    }

    /**
     * Adds data to model based on an Invoice that has been retrieved from
     * BitPay's API
     *
     * @param Bitpay\Invoice $invoice
     * @return Bitpay_Core_Model_Invoice
     */
    public function prepareWithBitpayInvoice($invoice)
    {
        $this->addData(
            array(
                'id'               => $invoice->getId(),
                //'updated_at'       => 'NOW()',
                'url'              => $invoice->getUrl(),
                'pos_data'         => $invoice->getPosData(),
                'status'           => $invoice->getStatus(),
                'btc_price'        => $invoice->getBtcPrice(),
                //'btc_due'          => $invoice->getBtcDue(),
                'price'            => $invoice->getPrice(),
                'currency'         => $invoice->getCurrency()->getCode(),
                //'ex_rates'         => $invoice->getExRates(),
                'order_id'         => $invoice->getOrderId(),
                'invoice_time'     => $invoice->getInvoiceTime(),
                'expiration_time'  => $invoice->getExpirationTime(),
                'current_time'     => $invoice->getCurrentTime(),
                'btc_paid'         => $invoice->getBtcPaid(),
                'rate'             => $invoice->getRate(),
                'exception_status' => $invoice->getExceptionStatus(),
                //'token'            => $invoice->getToken(),
            )
        );

        return $this;
    }

    /**
     * Adds information to based on the order object inside magento
     *
     * @param Mage_Sales_Model_Order $order
     * @return Bitpay_Core_Model_Invoice
     */
    public function prepateWithOrder($order)
    {
        $this->addData(
            array(
                'quote_id'     => $order->getQuoteId(),
                'increment_id' => $order->getIncrementId(),
            )
        );

        return $this;
    }
}
