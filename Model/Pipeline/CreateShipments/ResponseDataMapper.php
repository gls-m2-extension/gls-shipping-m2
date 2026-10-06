<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\Pipeline\CreateShipments;

use GlsGroup\Sdk\ParcelProcessing\Api\Data\ShipmentInterface as ApiShipment;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentDocumentInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentDocumentInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentResponseInterface;

/**
 * Convert API response into the carrier response format that the shipping module understands.
 *
 * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::requestToShipment
 */
class ResponseDataMapper
{
    /**
     * @var ShipmentDocumentInterfaceFactory
     */
    private $shipmentDocumentFactory;

    /**
     * @var LabelResponseInterfaceFactory
     */
    private $shipmentResponseFactory;

    /**
     * @var ShipmentErrorResponseInterfaceFactory
     */
    private $errorResponseFactory;

    public function __construct(
        ShipmentDocumentInterfaceFactory $shipmentDocumentFactory,
        LabelResponseInterfaceFactory $shipmentResponseFactory,
        ShipmentErrorResponseInterfaceFactory $errorResponseFactory
    ) {
        $this->shipmentDocumentFactory = $shipmentDocumentFactory;
        $this->shipmentResponseFactory = $shipmentResponseFactory;
        $this->errorResponseFactory = $errorResponseFactory;
    }

    /**
     * Map created shipment into response object as required by the shipping module.
     *
     * Notes:
     * - The carrier module will only ever request one parcel per shipment, thus only one parcel will be returned.
     * - The web service returns multiple labels only for certain scenarios not supported by the carrier module.
     *
     * @param ApiShipment $shipment
     * @param ShipmentInterface $salesShipment
     * @return LabelResponseInterface
     */
    public function createShipmentResponse(
        ApiShipment $shipment,
        ShipmentInterface $salesShipment
    ): LabelResponseInterface {
        $packages = $shipment->getParcels();
        $package = array_shift($packages);

        $labels = $shipment->getLabels();
        $label = array_shift($labels);

        $labelDoc = $this->shipmentDocumentFactory->create([
            'data' => [
                ShipmentDocumentInterface::TITLE => 'PDF Label',
                ShipmentDocumentInterface::MIME_TYPE => 'application/pdf',
                ShipmentDocumentInterface::LABEL_DATA => $label,
            ]
        ]);

        $responseData = [
            ShipmentResponseInterface::REQUEST_INDEX => $package->getParcelNumber(),
            ShipmentResponseInterface::SALES_SHIPMENT => $salesShipment,
            LabelResponseInterface::TRACKING_NUMBER => $package->getParcelNumber(),
            LabelResponseInterface::SHIPPING_LABEL_CONTENT => $label,
            LabelResponseInterface::DOCUMENTS => [$labelDoc],
        ];

        return $this->shipmentResponseFactory->create(['data' => $responseData]);
    }

    /**
     * Map error message into response object as required by the shipping module.
     *
     * @param string $requestIndex
     * @param Phrase $message
     * @param ShipmentInterface $salesShipment
     * @return ShipmentErrorResponseInterface
     */
    public function createErrorResponse(
        string $requestIndex,
        Phrase $message,
        ShipmentInterface $salesShipment
    ): ShipmentErrorResponseInterface {
        $responseData = [
            ShipmentResponseInterface::REQUEST_INDEX => $requestIndex,
            ShipmentResponseInterface::SALES_SHIPMENT => $salesShipment,
            ShipmentErrorResponseInterface::ERRORS => [$message],
        ];

        return $this->errorResponseFactory->create(['data' => $responseData]);
    }
}
