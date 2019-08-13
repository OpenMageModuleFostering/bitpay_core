<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */
$this->startSetup();

/**
 * IPN Log Table, used to keep track of incoiming IPNs
 */
$ipnTable = new Varien_Db_Ddl_Table();
$ipnTable->setName($this->getTable('bitpay/ipn'));
$this->getConnection()->changeColumn($ipnTable, 'curent_time', 'current_time', array('type' => Varien_Db_Ddl_Table::TYPE_INTEGER));

$this->endSetup();