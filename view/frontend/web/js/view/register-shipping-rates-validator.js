/**
 * See LICENSE.md for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        'GlsGroup_Shipping/js/model/shipping-rates-validator',
        'GlsGroup_Shipping/js/model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        shippingRatesValidator,
        shippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('glsgroup', shippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('glsgroup', shippingRatesValidationRules);

        return Component;
    }
);
