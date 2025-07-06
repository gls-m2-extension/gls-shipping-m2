<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use GlsGroup\Shipping\Model\Carrier\GlsGroup;
use GlsGroup\Shipping\Model\Config\Source\TermsOfTrade;
use GlsGroup\Shipping\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\OptionInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;

class InputDataProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var TermsOfTrade
     */
    private $termsOfTrade;

    /**
     * @var OptionInterfaceFactory
     */
    private $optionFactory;

    public function __construct(TermsOfTrade $termsOfTrade, OptionInterfaceFactory $optionFactory)
    {
        $this->termsOfTrade = $termsOfTrade;
        $this->optionFactory = $optionFactory;
    }

    /**
     * Set options and values to inputs on package level.
     *
     * @param ShippingOptionInterface $shippingOption
     */
    private function processInputs(ShippingOptionInterface $shippingOption)
    {
        foreach ($shippingOption->getInputs() as $input) {
            if ($input->getCode() === Codes::PACKAGING_INPUT_TERMS_OF_TRADE) {
                $fnCreateOptions = function (array $optionArray) {
                    $option = $this->optionFactory->create();
                    $option->setValue((string) $optionArray['value']);
                    $option->setLabel((string) $optionArray['label']);
                    return $option;
                };

                $input->setOptions(array_map($fnCreateOptions, $this->termsOfTrade->toOptionArray()));
            }
        }
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

        if (!$shipment) {
            return $shippingOptions;
        }

        foreach ($shippingOptions as $shippingOption) {
            $this->processInputs($shippingOption);
        }

        return $shippingOptions;
    }
}
