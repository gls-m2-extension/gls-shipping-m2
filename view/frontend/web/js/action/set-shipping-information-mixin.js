define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'GlsGroup_Shipping/js/model/current-relay-point',
    'GlsGroup_Shipping/js/view/selected-relay-point',
], function ($, wrapper, quote, currentRelayPoint, selectRelayPoint) {
    'use strict';

    return function (setShippingInformationAction) {
        return wrapper.wrap(setShippingInformationAction, function (originalAction) {

            var shippingAddress = quote.shippingAddress();
            if (shippingAddress['extension_attributes'] === undefined) {
                shippingAddress['extension_attributes'] = {};
            }
            if (currentRelayPoint()?.id && quote.shippingMethod()?.method_code === 'parcelshop' && quote.shippingMethod()?.carrier_code === 'glsgroup') {
                shippingAddress['extension_attributes']['relay_point'] = {
                    id: currentRelayPoint().id,
                    name: currentRelayPoint().name,
                    address: currentRelayPoint().address,
                    city: currentRelayPoint().city,
                    zipcode: currentRelayPoint().zipcode
                };
            }
            if (!currentRelayPoint()?.id && quote.shippingMethod()?.method_code === 'parcelshop' && quote.shippingMethod()?.carrier_code === 'glsgroup') {
                selectRelayPoint().showPopup();
                return $.Deferred().reject('Please select a relay point').promise()
            }
            // pass execution to original action ('Magento_Checkout/js/action/set-shipping-information')
            return originalAction();
        });
    };
});
