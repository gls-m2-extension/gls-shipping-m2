define(
    [
        'jquery',
        'mage/translate',
        'Magento_Ui/js/modal/modal',
        'ko',
        'uiComponent',
        'Magento_Checkout/js/model/quote',
        'GlsGroup_Shipping/js/model/current-relay-point'
    ],
    function (
        $, $t, modal, ko,
        Component,
        quote,
        currentRelayPoint) {
        return Component.extend(
            {
                defaults: {
                    template: 'GlsGroup_Shipping/selected-relay-point'
                },
                showPopup: () => {
                    $('#popup-select-relaypoint').modal(
                        {
                            buttons: [],
                            title: $t('Select a pick-up location'),
                            responsive: true,
                            modalLeftMargin: 0,

                        }
                    );
                    $('#popup-select-relaypoint').modal('openModal');
                },

                initialize: function () {
                    this._super();
                    this.relayPoint = currentRelayPoint;
                    this.shippingMethod = quote.shippingMethod;

                    this.showPopup();
                }
            }
        );
    }
);
