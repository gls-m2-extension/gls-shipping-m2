GLS Shipping Carrier Extension
==============================

The GLS extension for Magento® 2 integrates the
_GLS Web API for Parcel Processing & Cancellation_
APIs into the order processing workflow.

Description
-----------
This extension enables merchants to request and cancel shipping labels for incoming orders
via the GLS Web APIs.

Requirements
------------
* PHP >= 7.1.3
* PHP JSON extension
* PHP INTL extension

Compatibility
-------------
* Magento >= 2.3.0+
* Magento >= 2.4.0+

Installation Instructions
-------------------------

Install sources:

    composer require gls/shipping-m2

Enable module:

    ./bin/magento module:enable GlsGroup_Shipping
    ./bin/magento setup:upgrade

Flush cache and compile:

    ./bin/magento cache:flush
    ./bin/magento setup:di:compile

Sandbox Mode
------------

For development purposes, web service requests can be sent to the
testing endpoint by enabling sandbox mode via database query:

```sql
INSERT INTO core_config_data (path, value) VALUES ('carriers/glsgroup/account/sandboxmode', '1');
```

Note that the API credentials of a regular GLS account are valid for
the production endpoint only and will not work in sandbox mode.

Uninstallation
--------------

To unregister the carrier module from the application, run the following command:

    ./bin/magento module:uninstall --remove-data GlsGroup_Shipping
    composer update

This will automatically remove source files, clean up the database, update package dependencies.

Support
-------
In case of questions or problems, please have a look at the
[Support Portal (FAQ)](<"support portal url goes here">) first.

If the issue cannot be resolved, you can contact the support team by sending an email
to <gls.m2.extension@gmail.com>.

License
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2022 General Logistics Systems Germany GmbH & Co. OHG
