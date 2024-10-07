/**
 * See LICENSE.md for license details.
 */
define(
    [
        'GlsGroup_Shipping/js/model/relay-point-collection',
        'GlsGroup_Shipping/js/action/set-current-relay-point',
        'mage/storage'],
    function (relayPointCollection, setCurrentRelayPoint, storage) {
        "use strict";
        return function (q, countryId) {
            if (q) {
                relayPointCollection.isLoading(true);

                countryId = countryId ?? 'DE';

                storage.get(
                    'rest/V1/relaypoints/countryId/' + countryId + '/q/' + q
                )
                    .done(
                        function (data) {
                            relayPointCollection.isLoading(false);
                            if (data.length) {
                                relayPointCollection.setItems(data);
                            } else {
                                relayPointCollection.setItems([]);
                            }
                        }
                    );
            }
        };
    }
);
