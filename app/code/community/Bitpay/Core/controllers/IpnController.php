<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * @route /bitpay/ipn
 */
class Bitpay_Core_IpnController extends Mage_Core_Controller_Front_Action
{
    /**
     * bitpay's IPN lands here
     *
     * @route /bitpay/ipn
     * @route /bitpay/ipn/index
     */
    public function indexAction()
    {
        Mage::helper('bitpay')->registerAutoloader();
        Mage::helper('bitpay')->debugData(
            array(
                sprintf('Incoming IPN from bitpay'),
                getallheaders(),
                file_get_contents('php://input'),
            )
        );

        // Magento doesn't seem to have a way to get the Request body
        $ipn              = json_decode(file_get_contents('php://input'));
        $ipn->posData     = is_string($ipn->posData) ? json_decode($ipn->posData) : $ipn->posData;
        $ipn->buyerFields = isset($ipn->buyerFields) ? $ipn->buyerFields : new stdClass();
        Mage::helper('bitpay')->debugData($ipn);

        // Log IPN
        $mageIpn = Mage::getModel('bitpay/ipn')->addData(
            array(
                'invoice_id'       => isset($ipn->id) ? $ipn->id : '',
                'url'              => isset($ipn->url) ? $ipn->url : '',
                'pos_data'         => json_encode($ipn->posData),
                'status'           => isset($ipn->status) ? $ipn->status : '',
                'btc_price'        => isset($ipn->btcPrice) ? $ipn->btcPrice : '',
                'price'            => isset($ipn->price) ? $ipn->price : '',
                'currency'         => isset($ipn->currency) ? $ipn->currency : '',
                'invoice_time'     => isset($ipn->invoiceTime) ? $ipn->invoiceTime : '',
                'expiration_time'  => isset($ipn->expirationTime) ? $ipn->expirationTime : '',
                'current_time'     => isset($ipn->currentTime) ? $ipn->currentTime : '',
                'btc_paid'         => isset($ipn->btcPaid) ? $ipn->btcPaid : '',
                'rate'             => isset($ipn->rate) ? $ipn->rate : '',
                'exception_status' => isset($ipn->exceptionStatus) ? $ipn->exceptionStatus : '',
            )
        )->save();

        if (!isset($ipn->posData->id)) {
            Mage::helper('bitpay')->debugData(
                sprintf('Did not receive order id in IPN. See IPN "%s" in database.', $mageIpn->getId())
            );
            throw new Exception('Invalid Bitpay IPN received.');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($ipn->posData->id);

        if (!$order->getId()) {
            Mage::helper('bitpay')->debugData('Invalid Bitpay IPN received.');
            throw new Exception('Invalid Bitpay IPN received.');
        }

        // Update the order to notifiy that it has been paid
        if (in_array($ipn->status, array('paid', 'confirmed', 'complete'))) {
            $payment = Mage::getModel('sales/order_payment')->setOrder($order);
            $payment->registerCaptureNotification($ipn->price);
            $order->addPayment($payment)->save();
        }

        // use state as defined by Merchant
        $state = Mage::getStoreConfig(sprintf('payment/bitpay/invoice_%s', $ipn->status));
        $order->addStatusToHistory(
            $state,
            sprintf('Incoming IPN status "%s" updateded order state to "%s"', $ipn->status, $state)
        )->save();
    }
}
