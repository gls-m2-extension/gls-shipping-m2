/**
 * See LICENSE.md for license details.
 */
define(
    ['ko'],
    function (ko) {
            'use strict';
            var relayPoints = ko.observable([])
            var isLoading = ko.observable(true);
            return {
                isLoading: isLoading,
                setItems: function (items) {
                    relayPoints(items)
                },
                getItems: function () {
                    return relayPoints;
                }
        };
    }
);

