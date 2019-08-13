<?php
/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License
 * @see https://github.com/bitpay/magento-plugin/blob/master/LICENSE
 */

/**
 * This is used to display php extensions and if they are installed or not
 */
class Bitpay_Core_Block_Adminhtml_System_Config_Form_Field_Extension extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $phpExtension = $element->getFieldConfig()->php_extension;

        if (in_array($phpExtension, get_loaded_extensions())) {
            return 'Installed';
        }

        return 'Not Installed';
    }
}
