<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use GlsGroup\Shipping\Model\Carrier\GlsGroup;
use GlsGroup\Shipping\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;
use Netresearch\ShippingCore\Api\Util\DeliveryAreaInterface;

class ServiceAvailabilityProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var DeliveryAreaInterface
     */
    private $deliveryArea;

    public function __construct(DeliveryAreaInterface $deliveryArea)
    {
        $this->deliveryArea = $deliveryArea;
    }

    /**
     * In addition to the shipping routes, perform area-based availability checks.
     *
     * Some services may not be available in certain areas. Addresses on islands
     * for instance do not apply for express delivery etc.
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

        if (!$this->deliveryArea->isIsland($countryCode, $postalCode)) {
            return $shippingOptions;
        }

        // GLS services unavailable on islands
        $services = [
            Codes::SERVICE_OPTION_DEPOSIT,
            Codes::SERVICE_OPTION_FLEX_DELIVERY,
            Codes::SERVICE_OPTION_GUARANTEED24,
            Codes::SERVICE_OPTION_LETTERBOX,
            Codes::SERVICE_OPTION_SHOP_RETURN
        ];

        return array_filter(
            $shippingOptions,
            static function (ShippingOptionInterface $shippingOption) use ($services) {
                return !in_array($shippingOption->getCode(), $services, true);
            }
        );
    }
}
