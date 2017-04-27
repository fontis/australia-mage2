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
/*browser:true*/
/*global define*/
define(
    [
        "uiComponent",
        "Magento_Checkout/js/model/payment/renderer-list"
    ],
    function (
        Component,
        rendererList
    ) {
        "use strict";
        rendererList.push(
            {
                type: "fontis_australia_bpay",
                component: "Fontis_Australia/js/view/payment/method-renderer/fontis-australia/bpay-method"
            },
            {
                type: "fontis_australia_directdeposit",
                component: "Fontis_Australia/js/view/payment/method-renderer/fontis-australia/directdeposit-method"
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
