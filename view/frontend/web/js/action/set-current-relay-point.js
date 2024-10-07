/**
 * See LICENSE.md for license details.
 */
define(
    [
            'GlsGroup_Shipping/js/model/current-relay-point',
        ],
    function (currentRelayPoint) {
            "use strict";
            return function (relayPoint) {
                currentRelayPoint(relayPoint);

            }
    }
);
