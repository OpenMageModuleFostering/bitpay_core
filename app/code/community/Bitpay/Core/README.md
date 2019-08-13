bitpay/magento-plugin
=====================

# Build Status

[![Build Status](https://travis-ci.org/bitpay/magento-plugin.svg?branch=master)](https://travis-ci.org/bitpay/magento-plugin)

# Brief Description

BitPay has the most powerful bitcoin infrastructure on the planet and is trusted by over 40,000 merchants.

# Detail Description

Bitcoin is a powerful new peer-to-peer platform for the next generation of
financial technology. The decentralized nature of the Bitcoin network allows
for a highly resilient value transfer infrastructure, and this allows merchants
to gain greater profits.

This is because there are little to no fees for transferring Bitcoins from one
person to another. Unlike other payment methods, Bitcoin payments cannot be
reversed, so once you are paid you can ship! No waiting days for a payment to
clear.

# Requirements

* [Magento](http://magento.com/resources/system-requirements) >= 1.9.0.1 (Older version will work, but we do not test against those)
* [GMP](http://us2.php.net/gmp) You may have to install this as most servers do not come with it.
* [mcrypt](http://us2.php.net/mcrypt) Magento requires this so you're fine
* [OpenSSL](http://us2.php.net/openssl) Must be compiled with PHP
* PHP >= 5.4

# Upgrade From Version 1.x to 2.x

Merchants who have previous been using the BitPay Magento plugin will need to remove
previous plugin. This can be done by using the [scripts/delete](https://github.com/bitpay/magento-plugin/blob/master/scripts/delete)
script before installing the new plugin.

# Installation

## Magento Connect Manager

Goto [http://www.magentocommerce.com/magento-connect/bitpay-payment-method.html](http://www.magentocommerce.com/magento-connect/bitpay-payment-method.html)
and click the *Install Now* link which will give you the *Extension Key* needed
for the next step.

Once you have the key, log into you Magento Store's Admin Panel and navigate to
**System > Magento Connect > Magento Connect Manager**.

***NOTE*** It may ask you to log in again using the same credentials that you use
to log into the Admin Panel.

All you need to do is paste the extension key and click on the *Install* button.

***WARNING*** It is good practice to backup your database before installing
extensions. Please make sure you Create Backups.

## Download

Visit the [Releases](https://github.com/bitpay/magento-plugin/releases) page of
this repository and download the latest version. Once this is done, you can just
unzip the contents and use any method you want to put them on your server. The
contents will mirror the Magento directory structure.

***NOTE*** These files can also up uploaded using the *Magento Connect Manager*
that comes with your Magento Store

***WARNING*** It is good practice to backup your database before installing
extensions. Please make sure you Create Backups.

## modman

Using [modman](https://github.com/colinmollenhour/modman) you can
install the BitPay Magento Plugin. Once
you have modman installed, run `modman init` if you have not already done so. Next
just run `modman clone https://github.com/bitpay/magento-plugin.git` in the root
of the Magento installation. In this case it is `/var/www/magento`.

## Development

For developers wanting to contribute to this project, it is assumed you have a
stable Magento environment to work with, and are familiar with developing for
Magento. You will need to clone this repository or fork and clone the repository
you created.

Once you have cloned the repository, you will need to run [composer install](https://getcomposer.org/doc/00-intro.md#using-composer).
Using and setting up composer is outside the scope, however you will find the
documentation on their site comprehensive.

Once you are done, you can then run the ``scripts/package`` script to create a
distribution files which you can find in ``build/dist``. This is the file that
you can upload to your server to unzip or do with what you will.

If you encounter any issues or implement any updates or changes, please open an
[issue](https://github.com/bitpay/magento-plugin/issues) or submit a Pull Request.

***NOTE*** The ``scripts/package`` file contains some configuration settings that
will need to change for different releases. If you are using this script to build
files that are for distribution, these will need to be updated.

# Configuration

Configuration can be done using the Administrator section of your Megento store.
Once Logged in, you will find the configuration settings under **System > Configuration > Sales > Payment Methods**.

![BitPay Magento Settings](https://raw.githubusercontent.com/bitpay/magento-plugin/master/docs/MagentoSettings.png "BitPay Megento Settings")

Here your will need to create a [pairing code](https://bitpay.com/api-tokens) using
your BitPay merchant account. Once you have a Pairing Code, put the code in the
Pairing Code field. This will take care of the rest for you.

***NOTE*** Pairing Codes are only valid for a short period of time. If it expires
before you get to use it, you can always create a new one an use the new one.

***NOTE*** You will only need to do this once since each time you do this, the
extension will generate public and private keys that are used to identify you
when using the API.

You are also able to configure how BitPay's IPN (Instant Payment Notifications)
changes the order in your Magento store.

![BitPay Invoice Settings](https://raw.githubusercontent.com/bitpay/magento-plugin/master/docs/MagentoInvoiceSettings.png "BitPay Invoice Settings")

# Usage

Once enabled, your customers will be given the option to pay with Bitcoins. Once
they checkout they are redirected to a full screen BitPay invoice to pay for
the order.

As a merchant, the orders in your Magento store can be treated as any other
order. You may need to adjust the Invoice Settings depending on your order
fulfillment.

# Support

## BitPay Support

* [GitHub Issues](https://github.com/bitpay/magento-plugin/issues)
  * Open an issue if you are having issues with this plugin.
* [Support](https://support.bitpay.com)
  * BitPay merchant support documentation

## Magento Support

* [Homepage](http://magento.com)
* [Documentation](http://docs.magentocommerce.com)
* [Support Forums](https://www.magentocommerce.com/support/ce/)

# Troubleshooting

1. Ensure a valid SSL certificate is installed on your server. Also ensure your
   root CA cert is updated. If your CA cert is not current, you will see curl
   SSL verification errors.
2. Verify that your web server is not blocking POSTs from servers it may not
   recognize. Double check this on your firewall as well, if one is being used.
3. Check the `payment_bitpay.log` file for any errors during BitPay payment attempts.
   If you contact BitPay support, they will ask to see the log file to help
   diagnose the problem.  The log file will be found inside your Magento's
   `var/log/` directory. ***NOTE*** You will need to enable the debugging
   setting for the extension to output information into the log file.
4. Check the version of this plugin against the official plugin repository to
   ensure you are using the latest version. Your issue might have been
   addressed in a newer version! See the [Releases](https://github.com/bitpay/magento-plugin/releases)
   page for the latest.
5. If all else fails, send an email describing your issue **in detail** to
   support@bitpay.com

***TIP***: When contacting support it will help us is you provide:

* Magento Version (Found at the bottom page in the Administration section)
* Other extensions you have installed
  * Some extensions do not play nice
* Configuration settings for the extension (Most merchants take screen grabs)
* Any log files that will help
  * web server error logs
  * enabled debugging for this extension and send us `var/log/payment_bitpay.log`
* Screen grabs of error message if applicable.

# Contribute

To contribute to this project, please fork and submit a pull request.

# License

The MIT License (MIT)

Copyright (c) 2011-2014 BitPay, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
