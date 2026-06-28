# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## 3.0.0

### Added

- ShipIT REST API integration as a parallel label creation and cancellation path.
- OAuth2 client credentials authentication (per-store Client ID / Client Secret).
- New admin configuration section "ShipIt API (New)" with Enable toggle, Sandbox Mode, Client ID, Client Secret, and Contact ID fields.
- Support for all existing services via ShipIT: FlexDelivery, Guaranteed24, Deposit, Letterbox, ShopReturn, CashOnDelivery, and ParcelShop delivery.
- ShopReturn return label automatically extracted from the second PrintData entry and combined with the outbound label PDF.
- Incoterm code support for non-EU and intercontinental shipments.
- Shipment date calculation with cut-off time support on the ShipIT path.
- Weight minimum enforcement (0.1 kg) and unit conversion (lbs, g → kg) on the ShipIT path.

## 1.2.0

Magento 2.4.4 compatibility release

### Added

- Support for Magento 2.4.4

### Removed

- Support for PHP 7.1

## 1.1.1

### Changed

- Update version constraints to allow feature updates of the `netresearch/module-shipping-core` and `netresearch/module-shipping-ui` packages.

### Fixed

- Update label status for manually created shipments.

## 1.1.0

### Added

- Establish M2.4.3 compatibility.
- Add Incoterm Code 18 for UK shipments with a value up to 135 GBP.

### Fixed

- Terms of trade translations.

## 1.0.2

### Changed

- Update version constraints to allow feature updates of the `netresearch/module-shipping-core` and `netresearch/module-shipping-ui` packages.

## 1.0.1

### Changed

- Upgrade `netresearch/module-shipping-core` package to major version 2.

### Removed

- Remove sandbox mode setting from the module configuration.

### Fixed

- Update CSS selector in checkout service box.

## 1.0.0

Initial release
