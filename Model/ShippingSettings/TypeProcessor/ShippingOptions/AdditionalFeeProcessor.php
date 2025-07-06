<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use GlsGroup\Shipping\Model\AdditionalFee\ServiceAdjustmentProvider;
use GlsGroup\Shipping\Model\Carrier\GlsGroup;
use Magento\Framework\Pricing\Helper\Data as CurrencyConverter;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\InputInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;

/**
 * Append the service adjustment amount to the "enabled" input labels.
 */
class AdditionalFeeProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var ServiceAdjustmentProvider
     */
    private $feeProvider;

    /**
     * @var CurrencyConverter
     */
    private $currencyConverter;

    public function __construct(
        ServiceAdjustmentProvider $feeProvider,
        CurrencyConverter $currencyConverter
    ) {
        $this->feeProvider = $feeProvider;
        $this->currencyConverter = $currencyConverter;
    }

    private function updateInputLabel(InputInterface $input, float $amount, int $storeId): void
    {
        $amount = $this->currencyConverter->currencyByStore($amount, $storeId, true, false);
        $label = sprintf('%s (%s)', $input->getLabel(), $amount);
        $input->setLabel($label);
    }

    /**
     * @param string $carrierCode
     * @param ShippingOptionInterface[] $shippingOptions
     * @param int $storeId
     * @param string $countryCode
     * @param string $postalCode
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

        $fees = $this->feeProvider->getAmounts($storeId);

        foreach ($shippingOptions as $shippingOption) {
            if (!array_key_exists($shippingOption->getCode(), $fees)) {
                continue;
            }

            foreach ($shippingOption->getInputs() as $input) {
                if ($input->getCode() === 'enabled') {
                    $this->updateInputLabel($input, $fees[$shippingOption->getCode()], $storeId);
                }
            }
        }

        return $shippingOptions;
    }
}
