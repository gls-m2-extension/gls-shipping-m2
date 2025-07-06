<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use GlsGroup\Shipping\Model\Carrier\GlsGroup;
use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;

class Guaranteed24ServiceProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var ModuleConfig
     */
    private $config;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    public function __construct(
        ModuleConfig $config,
        TimezoneInterface $timezone
    ) {
        $this->config = $config;
        $this->timezone = $timezone;
    }

    /**
     * Remove the G24 service if delivery on the next day is not possible.
     *
     * The service must not be booked
     * - if no handover to the carrier happens anymore at the day of checkout
     * - on Friday, Saturday, Sunday (no next day delivery even before cut-off time)
     *
     * @param string $carrierCode
     * @param ShippingOptionInterface[] $shippingOptions
     * @param int $storeId
     * @param string $countryCode Destination country code
     * @param string $postalCode Destination postal code
     * @param ShipmentInterface|null $shipment
     *
     * @return ShippingOptionInterface[]
     */
    public function process(
        string $carrierCode,
        array $shippingOptions,
        int $storeId,
        string $countryCode,
        string $postalCode,
        ?ShipmentInterface $shipment = null
    ): array {
        if ($carrierCode !== GlsGroup::CARRIER_CODE) {
            // different carrier, nothing to modify.
            return $shippingOptions;
        }

        $guaranteed24Option = $shippingOptions[Codes::SERVICE_OPTION_GUARANTEED24] ?? false;
        if (!$guaranteed24Option) {
            // service not available for selection, nothing to modify.
            return $shippingOptions;
        }

        $currentDateTime =  $this->timezone->scopeDate($storeId, null, true);
        $weekDay = $currentDateTime->format('N');
        $shipmentDates = $this->config->getCutOffTimes($storeId);

        if (!isset($shipmentDates[$weekDay]) || $currentDateTime > $shipmentDates[$weekDay] || (int) $weekDay > 4) {
            unset($shippingOptions[Codes::SERVICE_OPTION_GUARANTEED24]);
        }

        return $shippingOptions;
    }
}
