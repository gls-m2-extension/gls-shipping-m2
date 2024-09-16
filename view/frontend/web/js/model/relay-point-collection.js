/**
 * Copyright © 2017 Ahmed Kooli. All rights reserved.
 * See COPYING.txt for license details.
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

