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

define([
    "jquery",
    "uiComponent",
    'mage/url',
    "jquery/ui"
], function($, Component, urlBuilder) {
    "use strict";

    var cityElementSelector = '[name=city]';
    var requestUrl = urlBuilder.build("australia/autocomplete/getpostcode");
    var initPostcodeAutocomplete = function(elementId) {
        var wrapperEle = $('#' + elementId);
        var cityInput = wrapperEle.find(cityElementSelector);

        if (cityInput.length === 0) {
            return false;
        }

        cityInput.parent().addClass("fontis-city-postcode-autocomplete");
        cityInput.parent().append('<div data-postcode-autocomplete-results="1"></div>');

        cityInput.autocomplete({
            source: requestUrl,
            minLength: 2,
            appendTo: "#" + elementId +" [data-postcode-autocomplete-results=1]",
            select: function(event, ui) {
                wrapperEle.find('[name=postcode]').val(ui.item.postcode)
            }
        });

        var countryInput = wrapperEle.find('[name=country_id]');

        // If country is not AU then disable autocomplete
        countryInput.change(function() {
            if ($(this).val() === 'AU') {
                cityInput.autocomplete('enable');
            } else {
                cityInput.autocomplete('disable');
            }
        });

        // Trigger change to check if autocomplete enable or not
        countryInput.trigger('change');

        return true;
    };

    // Set an interval to wait for the element to actually exist so we can modify it
    // we will remove the interval after the element has been modified
    var intervalCheckShippingCityInput = setInterval(function() {
        if (initPostcodeAutocomplete("co-shipping-form") === true) {
            // If city input found then we don't need this interval anymore
            clearInterval(intervalCheckShippingCityInput);
        }
    }, 2000);

    // Set an interval to wait for the element to actually exist so we can modify it
    // we will remove the interval after the element has been modified
    var intervalCheckPaymentCityInput = setInterval(function() {
        if (initPostcodeAutocomplete("payment") === true) {
            // If city input found then we don't need this interval anymore
            clearInterval(intervalCheckPaymentCityInput);
        }
    }, 2000);

    return Component.extend({});
});
