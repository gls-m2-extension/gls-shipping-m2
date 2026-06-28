<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\ShipmentDate\ShipmentDateCalculatorInterface;

/**
 * Maps a Magento shipment request to a ShipIt API ShipmentRequestData payload array.
 */
class RequestMapper
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var ShipmentDateCalculatorInterface
     */
    private $shipmentDateCalculator;

    public function __construct(
        ModuleConfig $moduleConfig,
        TimezoneInterface $timezone,
        ShipmentDateCalculatorInterface $shipmentDateCalculator
    ) {
        $this->moduleConfig           = $moduleConfig;
        $this->timezone               = $timezone;
        $this->shipmentDateCalculator = $shipmentDateCalculator;
    }

    /**
     * Map a Magento shipment request to a ShipIt ShipmentRequestData payload.
     *
     * @param Request $request
     * @return array ShipmentRequestData ready for JSON serialisation
     */
    public function mapRequest(Request $request): array
    {
        $storeId      = (int) $request->getOrderShipment()->getStoreId();
        $isParcelShop = $request->getShippingMethod() === 'parcelshop';

        return [
            'Shipment'        => $this->buildShipment($request, $storeId, $isParcelShop),
            'PrintingOptions' => [
                'ReturnLabels' => [
                    'TemplateSet' => 'NONE',
                    'LabelFormat' => 'PDF',
                ],
            ],
        ];
    }

    private function buildShipment(Request $request, int $storeId, bool $isParcelShop): array
    {
        $orderId = $request->getOrderShipment()->getOrder()->getIncrementId();

        $shipment = [
            'Middleware'        => 'Magento2ExtviaGLS',
            'Product'           => 'PARCEL',
            'ShipmentReference' => [substr($orderId, 0, 40)],
            'ShippingDate'      => $this->resolveShippingDate($storeId),
            'Shipper'           => $this->buildShipper($storeId),
            'Consignee'         => ['Address' => $this->buildConsigneeAddress($request, $isParcelShop)],
            'ShipmentUnit'      => $this->buildShipmentUnits($request),
        ];

        $incotermCode = $this->getIncotermCode($request);
        if ($incotermCode !== '') {
            $shipment['IncotermCode'] = $incotermCode;
        }

        $services = $this->buildServices($request, $isParcelShop);
        if (!empty($services)) {
            $shipment['Service'] = $services;
        }

        return $shipment;
    }

    /**
     * Read the merchant-selected terms of trade from the current package's customs params.
     *
     * The value is stored in packages[$packageId]['params']['customs']['termsOfTrade']
     * by the Magento checkout/shipment form — the same location the old API pipeline reads.
     * Returns an empty string when no customs data is present (domestic EU shipments).
     */
    private function getIncotermCode(Request $request): string
    {
        $packages  = (array) $request->getData('packages');
        $packageId = $request->getData('package_id');
        $customs   = $packages[$packageId]['params']['customs'] ?? [];

        return (string) ($customs['termsOfTrade'] ?? '');
    }

    /**
     * Calculate the next valid shipping date respecting the store's cut-off times.
     *
     * Delegates to ShipmentDateCalculatorInterface — the same calculator used by the
     * old ParcelProcessing pipeline — so that labels created after the daily cut-off
     * are automatically dated to the next business day.
     * Falls back to today's store-local date if no cut-off times are configured or
     * the calculator throws (e.g. no working days in the configured window).
     */
    private function resolveShippingDate(int $storeId): string
    {
        try {
            $date = $this->shipmentDateCalculator->getDate(
                $this->moduleConfig->getCutOffTimes($storeId),
                $storeId
            );
        } catch (\RuntimeException $e) {
            $date = $this->timezone->scopeDate($storeId);
        }

        return $date->format('Y-m-d');
    }

    private function buildShipper(int $storeId): array
    {
        return ['ContactID' => $this->moduleConfig->getShipItContactId($storeId)];
    }

    private function buildConsigneeAddress(Request $request, bool $isParcelShop): array
    {
        if ($isParcelShop) {
            // For parcel-shop deliveries the billing address is used, not the delivery address.
            $billing = $request->getOrderShipment()->getBillingAddress();
            $street  = implode(' ', array_filter($billing->getStreet()));
            $city    = $billing->getCity();
            $zipcode = $billing->getPostcode();
        } else {
            $street  = implode(' ', array_filter([
                $request->getRecipientAddressStreet1(),
                $request->getRecipientAddressStreet2(),
            ]));
            $city    = $request->getRecipientAddressCity();
            $zipcode = (string) $request->getRecipientAddressPostalCode();
        }

        $personName  = (string) $request->getRecipientContactPersonName();
        $companyName = (string) $request->getRecipientContactCompanyName();

        $address = [
            'Name1'       => substr($personName ?: $companyName, 0, 40),
            'Name2'       => $personName ? substr($companyName, 0, 40) : null,
            'CountryCode' => $request->getRecipientAddressCountryCode(),
            'City'        => $city,
            'Street'      => $street,
            'ZIPCode'     => $zipcode,
            // Email is always included when available: required by FlexDelivery service,
            // and harmless for all other shipment types.
            'eMail'       => $request->getOrderShipment()->getShippingAddress()->getEmail(),
        ];

        if ($isParcelShop) {
            $address['MobilePhoneNumber'] = (string) $request->getRecipientContactPhoneNumber();
        }

        return array_filter($address, static fn($v) => $v !== null && $v !== '');
    }

    /**
     * ShipIt API requires weight > 0.10 kg per parcel unit.
     */
    private const WEIGHT_MIN_KG = 0.1;

    private function buildShipmentUnits(Request $request): array
    {
        $storeId = (int) $request->getOrderShipment()->getStoreId();
        $orderId = $request->getOrderShipment()->getOrder()->getIncrementId();
        $units   = [];
        $codService = $this->buildCodService($request);

        foreach ((array) $request->getData('packages') as $package) {
            $weight    = (float) ($package['params']['weight'] ?? 0);
            $weightUom = (string) ($package['params']['weight_units'] ?? 'KILOGRAM');
            $weightKg  = $this->toKilogram($weight, $weightUom);
            if ($weightKg < self::WEIGHT_MIN_KG) {
                $weightKg = max($this->moduleConfig->getPackageDefaultWeight($storeId), self::WEIGHT_MIN_KG);
            }
            $unit = [
                'Weight' => round($weightKg, 3),
                'Note1'  => $orderId,
            ];
            if ($codService !== null) {
                $unit['Service'] = [$codService];
            }
            $units[] = $unit;
        }

        // Fallback when packages data is missing
        if (empty($units)) {
            $weightKg = (float) $request->getPackageWeight();
            if ($weightKg < self::WEIGHT_MIN_KG) {
                $weightKg = max($this->moduleConfig->getPackageDefaultWeight($storeId), self::WEIGHT_MIN_KG);
            }
            $unit = [
                'Weight' => round($weightKg, 3),
                'Note1'  => $orderId,
            ];
            if ($codService !== null) {
                $unit['Service'] = [$codService];
            }
            $units[] = $unit;
        }

        return $units;
    }

    /**
     * Build the Cash-on-Delivery service entry for a ShipmentUnit, or null if CoD is not selected.
     *
     * ShipIt API places CoD at the ShipmentUnit.Service level (not Shipment.Service).
     * The amount is the order's base grand total; the reason for payment comes from the
     * package's service params — the same location the old RequestDataMapper reads it.
     *
     * @return array|null  ['Cash' => [...]] ready for inclusion in ShipmentUnit.Service, or null
     */
    private function buildCodService(Request $request): ?array
    {
        $selectedServices = $this->getSelectedServices($request);

        if (empty($selectedServices['cashOnDelivery']['enabled'])) {
            return null;
        }

        $reason   = (string) ($selectedServices['cashOnDelivery']['reasonForPayment'] ?? '');
        $amount   = round((float) $request->getOrderShipment()->getOrder()->getBaseGrandTotal(), 2);
        $currency = (string) $request->getOrderShipment()->getOrder()->getBaseCurrencyCode();

        return [
            'Cash' => [
                'ServiceName' => 'service_cash',
                'Reason'      => $reason,
                'Amount'      => number_format($amount, 2, '.', ''),
                'Currency'    => $currency,
            ],
        ];
    }

    private function buildServices(Request $request, bool $isParcelShop): array
    {
        $services = [];

        if ($isParcelShop) {
            $parcelShopId = $request->getOrderShipment()->getShippingAddress()->getGlsRelayPointId();
            $services[] = [
                'ShopDelivery' => ['ServiceName' => 'service_shopdelivery', 'ParcelShopID' => $parcelShopId],
            ];
            // Shop delivery is mutually exclusive with other services
            return $services;
        }

        $selectedServices = $this->getSelectedServices($request);

        if (!empty($selectedServices['flexDelivery']['enabled'])) {
            $services[] = ['Service' => ['ServiceName' => 'service_flexdelivery']];
        }

        // Deposit and letterbox both map to the Deposit service in the ShipIt API.
        // Deposit (consumer-selected location) takes precedence over letterbox (merchant default),
        // matching the old RequestExtractor.getPlaceOfDeposit() behaviour.
        $placeOfDeposit = '';
        if (!empty($selectedServices['deposit']['enabled'])) {
            $placeOfDeposit = (string) ($selectedServices['deposit']['details'] ?? '');
        } elseif (!empty($selectedServices['letterBox']['enabled'])) {
            $placeOfDeposit = 'Briefkasten';
        }
        if ($placeOfDeposit !== '') {
            $services[] = ['Deposit' => ['ServiceName' => 'service_deposit', 'PlaceOfDeposit' => $placeOfDeposit]];
        }

        if (!empty($selectedServices['guaranteed24']['enabled'])) {
            $services[] = ['Service' => ['ServiceName' => 'service_guaranteed24']];
        }

        if (!empty($selectedServices['shopReturn']['enabled'])) {
            // ShipIt returns a second label (PrintData[1]) alongside the outbound label
            // when this service is booked. ShipItService::buildLabelResult() extracts it.
            $services[] = ['Service' => ['ServiceName' => 'service_shopreturn']];
        }

        return $services;
    }

    /**
     * Read service selections from the current package's params.
     *
     * The data structure mirrors what Netresearch ShippingCore stores in
     * packages[$packageId]['params']['services'] — no Netresearch import needed.
     */
    private function getSelectedServices(Request $request): array
    {
        $packages  = (array) $request->getData('packages');
        $packageId = $request->getData('package_id');

        return $packages[$packageId]['params']['services'] ?? [];
    }

    /**
     * Convert a weight value to kilograms.
     */
    private function toKilogram(float $weight, string $unitCode): float
    {
        switch (strtoupper($unitCode)) {
            case 'POUND':
            case 'LBS':
                return $weight * 0.453592;
            case 'GRAM':
            case 'G':
                return $weight / 1000.0;
            default: // KILOGRAM, KG, etc.
                return $weight;
        }
    }

}
