<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Shipping\Model\Shipment\Request;

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

    public function __construct(
        ModuleConfig $moduleConfig,
        TimezoneInterface $timezone
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->timezone     = $timezone;
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
            'ShippingDate'      => $this->timezone->scopeDate($storeId)->format('Y-m-d'),
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

        foreach ((array) $request->getData('packages') as $package) {
            $weight    = (float) ($package['params']['weight'] ?? 0);
            $weightUom = (string) ($package['params']['weight_units'] ?? 'KILOGRAM');
            $weightKg  = $this->toKilogram($weight, $weightUom);
            if ($weightKg < self::WEIGHT_MIN_KG) {
                $weightKg = max($this->moduleConfig->getPackageDefaultWeight($storeId), self::WEIGHT_MIN_KG);
            }
            $units[] = [
                'Weight' => round($weightKg, 3),
                'Note1'  => $orderId,
            ];
        }

        // Fallback when packages data is missing
        if (empty($units)) {
            $weightKg = (float) $request->getPackageWeight();
            if ($weightKg < self::WEIGHT_MIN_KG) {
                $weightKg = max($this->moduleConfig->getPackageDefaultWeight($storeId), self::WEIGHT_MIN_KG);
            }
            $units[] = [
                'Weight' => round($weightKg, 3),
                'Note1'  => $orderId,
            ];
        }

        return $units;
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

        if (!empty($selectedServices['deposit']['enabled'])) {
            $placeOfDeposit = (string) ($selectedServices['deposit']['details'] ?? '');
            if ($placeOfDeposit !== '') {
                $services[] = ['Deposit' => ['ServiceName' => 'service_deposit', 'PlaceOfDeposit' => $placeOfDeposit]];
            }
        }

        if (!empty($selectedServices['guaranteed24']['enabled'])) {
            $services[] = ['Service' => ['ServiceName' => 'service_guaranteed24']];
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
