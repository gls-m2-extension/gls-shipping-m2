<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Pipeline\CreateShipments\ShipmentRequest;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\Pipeline\CreateShipments\ShipmentRequest\Data\PackageAdditionalFactory;
use GlsGroup\Shipping\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\RecipientInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\ShipperInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\ShipperInterfaceFactory;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractor\ServiceOptionReaderInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractor\ServiceOptionReaderInterfaceFactory;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractorInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractorInterfaceFactory;

/**
 * Class RequestExtractor
 *
 * The original shipment request is a rather limited DTO with unstructured data (DataObject, array).
 * The extractor and its subtypes offer a well-defined interface to extract the request data and
 * isolates the toxic part of extracting unstructured array data from the shipment request.
 */
class RequestExtractor implements RequestExtractorInterface
{
    /**
     * @var Request
     */
    private $shipmentRequest;

    /**
     * @var RequestExtractorInterfaceFactory
     */
    private $requestExtractorFactory;

    /**
     * @var ServiceOptionReaderInterfaceFactory
     */
    private $serviceOptionReaderFactory;

    /**
     * @var PackageAdditionalFactory
     */
    private $packageAdditionalFactory;

    /**
     * @var PackageInterfaceFactory
     */
    private $packageFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var ShipperInterfaceFactory
     */
    private $shipperFactory;

    /**
     * @var RequestExtractorInterface
     */
    private $coreExtractor;

    /**
     * @var ServiceOptionReaderInterface
     */
    private $serviceOptionReader;

    /**
     * @var ShipperInterface
     */
    private $returnRecipient;

    public function __construct(
        Request $shipmentRequest,
        RequestExtractorInterfaceFactory $requestExtractorFactory,
        ServiceOptionReaderInterfaceFactory $serviceOptionReaderFactory,
        PackageAdditionalFactory $packageAdditionalFactory,
        PackageInterfaceFactory $packageFactory,
        ModuleConfig $moduleConfig,
        ShipperInterfaceFactory $shipperFactory
    ) {
        $this->shipmentRequest = $shipmentRequest;
        $this->requestExtractorFactory = $requestExtractorFactory;
        $this->serviceOptionReaderFactory = $serviceOptionReaderFactory;
        $this->packageAdditionalFactory = $packageAdditionalFactory;
        $this->packageFactory = $packageFactory;
        $this->moduleConfig = $moduleConfig;
        $this->shipperFactory = $shipperFactory;
    }

    /**
     * Obtain core extractor for forwarding generic shipment data calls.
     *
     * @return RequestExtractorInterface
     */
    private function getCoreExtractor(): RequestExtractorInterface
    {
        if (empty($this->coreExtractor)) {
            $this->coreExtractor = $this->requestExtractorFactory->create(
                ['shipmentRequest' => $this->shipmentRequest]
            );
        }

        return $this->coreExtractor;
    }

    /**
     * Obtain service option reader to read carrier specific service data.
     *
     * @return ServiceOptionReaderInterface
     */
    private function getServiceOptionReader(): ServiceOptionReaderInterface
    {
        if (empty($this->serviceOptionReader)) {
            $this->serviceOptionReader = $this->serviceOptionReaderFactory->create(
                ['shipmentRequest' => $this->shipmentRequest]
            );
        }

        return $this->serviceOptionReader;
    }

    public function isReturnShipmentRequest(): bool
    {
        return $this->getCoreExtractor()->isReturnShipmentRequest();
    }

    public function getStoreId(): int
    {
        return $this->getCoreExtractor()->getStoreId();
    }

    public function getBaseCurrencyCode(): string
    {
        return $this->getCoreExtractor()->getBaseCurrencyCode();
    }

    public function getOrder(): Order
    {
        return $this->getCoreExtractor()->getOrder();
    }

    public function getShipment(): Shipment
    {
        return $this->getCoreExtractor()->getShipment();
    }

    public function getShipper(): ShipperInterface
    {
        return $this->getCoreExtractor()->getShipper();
    }

    public function getReturnRecipient(): ShipperInterface
    {
        if (!empty($this->returnRecipient)) {
            return $this->returnRecipient;
        }

        $alternativeAddress = $this->moduleConfig->getAlternativeReturnAddress($this->getStoreId());
        if (empty($alternativeAddress)) {
            $this->returnRecipient = $this->getCoreExtractor()->getReturnRecipient();
        } else {
            $this->returnRecipient = $this->shipperFactory->create(
                [
                    'contactPersonName' => '',
                    'contactPersonFirstName' => '',
                    'contactPersonLastName' => '',
                    'contactCompanyName' => $alternativeAddress['company'],
                    'contactEmail' => '',
                    'contactPhoneNumber' => '',
                    'street' => [$alternativeAddress['street']],
                    'city' => $alternativeAddress['city'],
                    'state' => '',
                    'postalCode' => $alternativeAddress['postcode'],
                    'countryCode' => $alternativeAddress['country_id'],
                    'streetName' => '',
                    'streetNumber' => '',
                    'addressAddition' => '',
                ]
            );
        }

        return $this->returnRecipient;
    }

    public function getRecipient(): RecipientInterface
    {
        return $this->getCoreExtractor()->getRecipient();
    }

    public function getPackageWeight(): float
    {
        return $this->getCoreExtractor()->getPackageWeight();
    }

    public function getPackages(): array
    {
        $packages = $this->getCoreExtractor()->getPackages();
        $glsPackages = [];

        foreach ($packages as $packageId => $package) {
            // read generic export data from shipment request
            $packageParams = $this->shipmentRequest->getData('packages')[$packageId]['params'];
            $customsParams = $packageParams['customs'] ?? [];
            if (empty($customsParams)) {
                // GLS has only additional package params for customs, nothing to do.
                $glsPackages[$packageId] = $package;
                continue;
            }

            try {
                $additionalData['termsOfTrade'] = $customsParams['termsOfTrade'];

                // create new extended package instance with paket-specific export data
                $glsPackages[$packageId] = $this->packageFactory->create(
                    [
                        'productCode' => $package->getProductCode(),
                        'containerType' => $package->getContainerType(),
                        'weightUom' => $package->getWeightUom(),
                        'dimensionsUom' => $package->getDimensionsUom(),
                        'weight' => $package->getWeight(),
                        'length' => $package->getLength(),
                        'width' => $package->getWidth(),
                        'height' => $package->getHeight(),
                        'customsValue' => $package->getCustomsValue(),
                        'contentType' => $package->getContentType(),
                        'contentExplanation' => $package->getContentExplanation(),
                        'packageAdditional' => $this->packageAdditionalFactory->create($additionalData),
                    ]
                );
            } catch (\Exception $exception) {
                throw new LocalizedException(__('An error occurred while preparing parcel data.'), $exception);
            }
        }

        return $glsPackages;
    }

    public function getAllItems(): array
    {
        return $this->getCoreExtractor()->getAllItems();
    }

    public function getPackageItems(): array
    {
        return $this->getCoreExtractor()->getPackageItems();
    }

    public function isCashOnDelivery(): bool
    {
        return $this->coreExtractor->isCashOnDelivery();
    }

    public function getCodReasonForPayment(): string
    {
        return $this->coreExtractor->getCodReasonForPayment();
    }

    public function isPickupLocationDelivery(): bool
    {
        return $this->coreExtractor->isPickupLocationDelivery();
    }

    public function getDeliveryLocationType(): string
    {
        return $this->coreExtractor->getCodReasonForPayment();
    }

    public function getDeliveryLocationId(): string
    {
        return $this->coreExtractor->getDeliveryLocationId();
    }

    public function getDeliveryLocationNumber(): string
    {
        return $this->coreExtractor->getDeliveryLocationNumber();
    }

    public function getDeliveryLocationCountryCode(): string
    {
        return $this->coreExtractor->getDeliveryLocationCountryCode();
    }

    public function getDeliveryLocationPostalCode(): string
    {
        return $this->coreExtractor->getDeliveryLocationPostalCode();
    }

    public function getDeliveryLocationCity(): string
    {
        return $this->coreExtractor->getDeliveryLocationCity();
    }

    public function getDeliveryLocationStreet(): string
    {
        return $this->coreExtractor->getDeliveryLocationStreet();
    }

    /**
     * Check if recipient email must be set.
     *
     * By default, recipient email address is not included with the request.
     * There are some services though that require an email address.
     *
     * @return bool
     */
    public function isRecipientEmailRequired(): bool
    {
        if ($this->isFlexDeliveryEnabled() || $this->shipmentRequest->getShippingMethod() === 'parcelshop') {
            // flex delivery service requires email address
            return true;
        }

        return false;
    }

    /**
     * Check whether FlexDeliveryService was chosen or not.
     *
     * @return bool
     */
    public function isFlexDeliveryEnabled(): bool
    {
        return $this->getServiceOptionReader()->isServiceEnabled(Codes::SERVICE_OPTION_FLEX_DELIVERY);
    }

    /**
     * Check whether Guaranteed24Service was chosen or not.
     *
     * @return bool
     */
    public function isNextDayDeliveryEnabled(): bool
    {
        return $this->getServiceOptionReader()->isServiceEnabled(Codes::SERVICE_OPTION_GUARANTEED24);
    }

    /**
     * Check whether ShopReturnService was chosen or not.
     *
     * @return bool
     */
    public function isShopReturnBooked(): bool
    {
        return $this->getServiceOptionReader()->isServiceEnabled(Codes::SERVICE_OPTION_SHOP_RETURN);
    }

    /**
     * Obtain the alternative location to place the parcel.
     *
     * Note: The location selected by the consumer in checkout always
     * takes precedence over the merchant's service default setting.
     * That is, if the consumer commissions the courier to place the
     * parcel in the garage, the letterbox service will be ignored.
     *
     * @return string
     */
    public function getPlaceOfDeposit(): string
    {
        if ($this->getServiceOptionReader()->isServiceEnabled(Codes::SERVICE_OPTION_DEPOSIT)) {
            return $this->getServiceOptionReader()->getServiceOptionValue(
                Codes::SERVICE_OPTION_DEPOSIT,
                'details'
            );
        }

        if ($this->getServiceOptionReader()->isServiceEnabled(Codes::SERVICE_OPTION_LETTERBOX)) {
            return 'Briefkasten';
        }

        return '';
    }
}
