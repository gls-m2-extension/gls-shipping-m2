<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Pipeline\CreateShipments;

use GlsGroup\Sdk\ParcelProcessing\Api\LabelRequestBuilderInterface;
use GlsGroup\Sdk\ParcelProcessing\RequestBuilder\ReturnShipmentRequestBuilder;
use GlsGroup\Sdk\ParcelProcessing\Exception\RequestValidatorException;
use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\Config\Source\LabelSize;
use GlsGroup\Shipping\Model\Pipeline\CreateShipments\ShipmentRequest\Data\PackageAdditional;
use GlsGroup\Shipping\Model\Pipeline\CreateShipments\ShipmentRequest\RequestExtractorFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageInterface;
use Netresearch\ShippingCore\Api\Util\UnitConverterInterface;

class ReturnRequestDataMapper
{
    /**
     * @var RequestExtractorFactory
     */
    private $requestExtractorFactory;

    /**
     * @var ReturnShipmentRequestBuilder
     */
    private $requestBuilder;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var UnitConverterInterface
     */
    private $unitConverter;

    public function __construct(
        ReturnShipmentRequestBuilder $requestBuilder,
        RequestExtractorFactory $requestExtractorFactory,
        ModuleConfig $moduleConfig,
        UnitConverterInterface $unitConverter
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->requestExtractorFactory = $requestExtractorFactory;
        $this->moduleConfig = $moduleConfig;
        $this->unitConverter = $unitConverter;
    }

    /**
     * Map the Magento shipment request to an SDK request object using the SDK request builder.
     *
     * @param Request $request The shipment request
     *
     * @return \JsonSerializable|null
     * @throws LocalizedException
     */
    public function mapRequest(Request $request): ?\JsonSerializable
    {
        $requestExtractor = $this->requestExtractorFactory->create(['shipmentRequest' => $request]);
        if (!$requestExtractor->isShopReturnBooked()) {
            return null;
        }

        $implode = function (array $parts) {
            $parts = array_filter($parts);
            return implode(' ', $parts);
        };

        $this->requestBuilder->setShipperAccount(
            $this->moduleConfig->getShipperId($requestExtractor->getStoreId()),
            $this->moduleConfig->getBrokerReference()
        );

        $this->requestBuilder->setShipperAddress(
            $requestExtractor->getRecipient()->getCountryCode(),
            $requestExtractor->getRecipient()->getPostalCode(),
            $requestExtractor->getRecipient()->getCity(),
            $implode($requestExtractor->getRecipient()->getStreet()),
            $requestExtractor->getRecipient()->getContactPersonName(),
            $requestExtractor->getRecipient()->getContactCompanyName(),
            null,
            null,
            null,
            null,
            $requestExtractor->getRecipient()->getState()
        );

        $this->requestBuilder->setRecipientAddress(
            $requestExtractor->getReturnRecipient()->getCountryCode(),
            $requestExtractor->getReturnRecipient()->getPostalCode(),
            $requestExtractor->getReturnRecipient()->getCity(),
            $implode($requestExtractor->getReturnRecipient()->getStreet()),
            $requestExtractor->getReturnRecipient()->getContactCompanyName(),
            null,
            null,
            null,
            $requestExtractor->getReturnRecipient()->getState(),
            $requestExtractor->getReturnRecipient()->getContactPersonName()
        );

        /** @var PackageInterface $package */
        foreach ($requestExtractor->getPackages() as $package) {
            $weight = $package->getWeight();
            $weightUom = $package->getWeightUom();
            $weightInKg = $this->unitConverter->convertWeight($weight, $weightUom, 'KILOGRAM');

            $this->requestBuilder->addParcel(
                $weightInKg,
                false,
                $requestExtractor->getOrder()->getIncrementId()
            );

            $packageAdditional = $package->getPackageAdditional();
            if ($packageAdditional instanceof PackageAdditional && !empty($packageAdditional->getTermsOfTrade())) {
                $this->requestBuilder->setCustomsDetails((int) $packageAdditional->getTermsOfTrade());
            }
        }

        $this->requestBuilder->setLabelFormat(LabelRequestBuilderInterface::LABEL_FORMAT_PDF);

        $labelSize = $this->moduleConfig->getLabelSize($requestExtractor->getStoreId());
        if ($labelSize === LabelSize::LABEL_SIZE_A6) {
            $this->requestBuilder->setLabelSize(LabelRequestBuilderInterface::LABEL_SIZE_A6);
        } elseif ($labelSize === LabelSize::LABEL_SIZE_A5) {
            $this->requestBuilder->setLabelSize(LabelRequestBuilderInterface::LABEL_SIZE_A5);
        } elseif ($labelSize === LabelSize::LABEL_SIZE_A4) {
            $this->requestBuilder->setLabelSize(LabelRequestBuilderInterface::LABEL_SIZE_A4);
        }

        try {
            return $this->requestBuilder->create();
        } catch (RequestValidatorException $exception) {
            $message = __('GLS WebAPI request could not be created: %1', $exception->getMessage());
            throw new LocalizedException($message);
        }
    }
}
