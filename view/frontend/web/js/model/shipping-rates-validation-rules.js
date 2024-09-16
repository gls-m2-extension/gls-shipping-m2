/**
 * See LICENSE.md for license details.
 */
/*browser:true*/
/*global define*/
define(
    [],
    function () {
        'use strict';

        return {
            getRules: function () {
                console.log('validation rules')
                return {
                    'city': {
                        'required': false
                    },
                    'postcode': {
                        'required': false
                    },
                    'country_id': {
                        'required': false
                    }
                };
            }
        };
    }
);
