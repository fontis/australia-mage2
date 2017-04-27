/**
 * Fontis Australia Extension for Magento 2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_Australia
 * @copyright  Copyright (c) 2017 Fontis Pty. Ltd. (https://www.fontis.com.au)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(
    [
        "ko",
        "Magento_Checkout/js/view/payment/default",
        "mage/translate"
    ],
    function (ko, Component, $t) {
        "use strict";

        return Component.extend({
            defaults: {
                template: "Fontis_Australia/payment/fontis_australia/directdeposit"
            },

            /**
             * Get bank account details to display to the customer.
             *
             * @returns {String}
             */
            getInstructions: function() {
                var fields = [];
                var config = window.checkoutConfig.payment[this.item.method];

                fields.push($t("Account Name: %1").replace("%1", config.account_name));
                fields.push($t("Account BSB: %1").replace("%1", config.account_bsb));
                fields.push($t("Account Number: %1").replace("%1", config.account_number));
                if (config.message) {
                    fields.push('<p>' + config.message + '</p>');
                }

                return fields;
            }
        });
    }
);
