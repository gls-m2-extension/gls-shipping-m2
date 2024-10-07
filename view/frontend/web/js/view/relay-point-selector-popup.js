/**
 * See LICENSE.md for license details.
 */
define(['jquery', 'ko', 'mage/translate', 'uiComponent', 'Magento_Checkout/js/model/quote', 'GlsGroup_Shipping/js/model/relay-point-collection', 'GlsGroup_Shipping/js/model/current-relay-point', 'GlsGroup_Shipping/js/action/search-relay-points'], function ($, ko, $t, Component, quote, relayPointCollection, currentRelayPoint, searchRelayPoints) {

    return Component.extend({
        defaults: {
            template: 'GlsGroup_Shipping/relay-point-selector-popup'
        }, initialize: function () {

            var self = this;
            this._super();
            this.searchAddressPlaceholder = $t('Address, postcode or city');
            this.relayPoints = relayPointCollection.getItems();
            this.isLoading = relayPointCollection.isLoading;
            this.currentRelayPoint = currentRelayPoint;

            this.relayPoints.subscribe((items)=>{
                if(this.currentRelayPoint() === null && items.length > 0){
                    currentRelayPoint(items[0]);
                }
            })
            if(this.currentRelayPoint() === null && this.relayPoints.length > 0){
                currentRelayPoint(this.relayPoints[0]);
            }
            this.searchAddress = ko.observable(null);
            quote.shippingAddress.subscribe(function (address) {
                const postcode = address.postcode ?? '';
                const city = address.city ?? ''
                const street = (!address.street || !address.street[0] || address.street[0] === '') ? '' : address.street[0];
                // let countryId = address.countryId ?? '';
                const q = street + ' ' + postcode + ' ' + city
                self.searchAddress(q);
                searchRelayPoints(q,address.countryId);
            });
        }, setCurrentRelayPoint: function (relayPoint) {
            currentRelayPoint(relayPoint);
            $('#popup-select-relaypoint').modal('closeModal');
        },

        search: function () {
            searchRelayPoints(this.searchAddress());
        }
    });
});
