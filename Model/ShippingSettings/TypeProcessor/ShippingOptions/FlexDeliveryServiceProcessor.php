<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use GlsGroup\Shipping\Model\Carrier\GlsGroup;
use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;

class FlexDeliveryServiceProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var ModuleConfig
     */
    private $config;

    public function __construct(ModuleConfig $config)
    {
        $this->config = $config;
    }

    /**
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

        $flexDeliveryOption = $shippingOptions[Codes::SERVICE_OPTION_FLEX_DELIVERY] ?? false;
        if (!$flexDeliveryOption) {
            return $shippingOptions;
        }

        $isEnabledInput = $flexDeliveryOption->getInputs()['enabled'] ?? false;
        if (!$isEnabledInput) {
            return $shippingOptions;
        }

        $comment = $isEnabledInput->getComment();
        if (!$comment) {
            return $shippingOptions;
        }

        $commentText = $comment->getContent();
        $email = $this->config->getFlexDeliveryRevocationEmail($storeId);
        $comment->setContent(__($commentText, $email)->render());

        return $shippingOptions;
    }
}
