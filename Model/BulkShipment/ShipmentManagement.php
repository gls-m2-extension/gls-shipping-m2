<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\BulkShipment;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\Pipeline\ApiGateway;
use GlsGroup\Shipping\Model\Pipeline\ApiGatewayFactory;
use GlsGroup\Shipping\Model\ShipIt\ShipItService;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCancellationInterface;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCreationInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ReturnShipmentDocumentInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ReturnShipmentDocumentInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentDocumentInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackRequest\TrackRequestInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackErrorResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackResponseInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentResponseProcessorInterface;
use Netresearch\ShippingCore\Api\Pipeline\TrackResponseProcessorInterface;
use Netresearch\ShippingCore\Model\Pipeline\Shipment\ShipmentResponse\ErrorResponseFactory;
use Netresearch\ShippingCore\Model\Pipeline\Shipment\ShipmentResponse\LabelResponseFactory;
use Netresearch\ShippingCore\Model\Pipeline\Track\TrackResponse\TrackErrorResponseFactory;
use Netresearch\ShippingCore\Model\Pipeline\Track\TrackResponse\TrackResponseFactory;

/**
 * Class ShipmentManagement
 *
 * Central entrypoint for creating and deleting shipments.
 */
class ShipmentManagement implements BulkLabelCreationInterface, BulkLabelCancellationInterface
{
    /**
     * @var ApiGatewayFactory
     */
    private $apiGatewayFactory;

    /**
     * @var ShipmentResponseProcessorInterface
     */
    private $createResponseProcessor;

    /**
     * @var TrackResponseProcessorInterface
     */
    private $deleteResponseProcessor;

    /**
     * @var ApiGateway[]
     */
    private $apiGateways;

    /**
     * @var ShipItService
     */
    private $shipItService;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var LabelResponseFactory
     */
    private $labelResponseFactory;

    /**
     * @var ErrorResponseFactory
     */
    private $errorResponseFactory;

    /**
     * @var TrackResponseFactory
     */
    private $trackResponseFactory;

    /**
     * @var TrackErrorResponseFactory
     */
    private $trackErrorResponseFactory;

    /**
     * @var ReturnShipmentDocumentInterfaceFactory
     */
    private $returnDocumentFactory;

    /**
     * @var LabelGenerator
     */
    private $labelGenerator;

    public function __construct(
        ApiGatewayFactory $apiGatewayFactory,
        ShipmentResponseProcessorInterface $createResponseProcessor,
        TrackResponseProcessorInterface $deleteResponseProcessor,
        ShipItService $shipItService,
        ModuleConfig $moduleConfig,
        TrackResponseFactory $trackResponseFactory,
        TrackErrorResponseFactory $trackErrorResponseFactory,
        LabelResponseFactory $labelResponseFactory,
        ErrorResponseFactory $errorResponseFactory,
        ReturnShipmentDocumentInterfaceFactory $returnDocumentFactory,
        LabelGenerator $labelGenerator
    ) {
        $this->apiGatewayFactory         = $apiGatewayFactory;
        $this->createResponseProcessor   = $createResponseProcessor;
        $this->deleteResponseProcessor   = $deleteResponseProcessor;
        $this->shipItService             = $shipItService;
        $this->moduleConfig              = $moduleConfig;
        $this->trackResponseFactory      = $trackResponseFactory;
        $this->trackErrorResponseFactory = $trackErrorResponseFactory;
        $this->labelResponseFactory      = $labelResponseFactory;
        $this->errorResponseFactory      = $errorResponseFactory;
        $this->returnDocumentFactory     = $returnDocumentFactory;
        $this->labelGenerator            = $labelGenerator;
    }

    /**
     * Create api gateway.
     *
     * API gateways are created with store specific configuration and configured post-processors (bulk or popup).
     *
     * @param int $storeId
     * @return ApiGateway
     */
    private function getApiGateway(int $storeId): ApiGateway
    {
        if (!isset($this->apiGateways[$storeId])) {
            $api = $this->apiGatewayFactory->create(
                [
                    'storeId' => $storeId,
                    'createResponseProcessor' => $this->createResponseProcessor,
                    'deleteResponseProcessor' => $this->deleteResponseProcessor,
                ]
            );

            $this->apiGateways[$storeId] = $api;
        }

        return $this->apiGateways[$storeId];
    }

    /**
     * Create shipment labels at GLS API
     *
     * Shipment requests are divided by store for multi-store support (different GLS account configurations).
     *
     * @param Request[] $shipmentRequests
     * @return ShipmentResponseInterface[]
     */
    public function createLabels(array $shipmentRequests): array
    {
        if (empty($shipmentRequests)) {
            return [];
        }

        $apiRequests = [];
        $apiResults = [];

        foreach ($shipmentRequests as $shipmentRequest) {
            $storeId = (int) $shipmentRequest->getOrderShipment()->getStoreId();
            $apiRequests[$storeId][] = $shipmentRequest;
        }

        foreach ($apiRequests as $storeId => $storeApiRequests) {
            if ($this->moduleConfig->isShipItEnabled($storeId)) {
                $rawResults = $this->shipItService->createShipments($storeApiRequests, $storeId);
                $apiResults[$storeId] = $this->convertShipItCreateResults($rawResults, $storeApiRequests);
            } else {
                $apiResults[$storeId] = $this->getApiGateway($storeId)->createShipments($storeApiRequests);
            }
        }

        if (!empty($apiResults)) {
            // convert results per store to flat response
            $apiResults = array_reduce($apiResults, 'array_merge', []);
        }

        return $apiResults;
    }

    /**
     * Cancel shipment orders at the GLS API alongside associated tracks and shipping labels.
     *
     * Cancellation requests are divided by store for multi-store support (different GLS account configurations).
     *
     * @param TrackRequestInterface[] $cancelRequests
     * @return TrackResponseInterface[]
     */
    public function cancelLabels(array $cancelRequests): array
    {
        if (empty($cancelRequests)) {
            return [];
        }

        $apiRequests = [];
        $apiResults = [];

        // divide cancel requests by store as they may use different api configurations
        foreach ($cancelRequests as $shipmentNumber => $cancelRequest) {
            $storeId = $cancelRequest->getStoreId();
            $apiRequests[$storeId][$shipmentNumber] = $cancelRequest;
        }

        foreach ($apiRequests as $storeId => $storeApiRequests) {
            if ($this->moduleConfig->isShipItEnabled($storeId)) {
                $rawResults = $this->shipItService->cancelShipments(array_keys($storeApiRequests), $storeId);
                $apiResults[$storeId] = $this->convertShipItCancelResults($rawResults, $storeApiRequests);
            } else {
                $apiResults[$storeId] = $this->getApiGateway($storeId)->cancelShipments($storeApiRequests);
            }
        }

        if (!empty($apiResults)) {
            // convert results per store to flat response
            $apiResults = array_reduce($apiResults, 'array_merge', []);
        }

        return $apiResults;
    }

    /**
     * Convert ShipIt create DataObject results into ShipmentResponseInterface objects,
     * then run the response processor to add tracks and labels to the shipment entities.
     *
     * Results are returned in the same order as the input requests.
     *
     * When a ShopReturn label was requested the DataObject carries a second field
     * 'return_label_content' (raw PDF bytes). In that case the return PDF is wrapped
     * in a ReturnShipmentDocument and the two PDFs are combined into the
     * shipping_label_content — matching the behaviour of CreateShopReturnLabelStage
     * in the old ParcelProcessing pipeline.
     *
     * @param DataObject[] $shipItResults  Plain DataObjects from ShipItService
     * @param Request[]    $requests       Original shipment requests in the same order
     * @return ShipmentResponseInterface[]
     */
    private function convertShipItCreateResults(array $shipItResults, array $requests): array
    {
        $labelResponses = [];
        $errorResponses = [];
        $requests       = array_values($requests);

        foreach ($shipItResults as $index => $dataObject) {
            $shipment = isset($requests[$index]) ? $requests[$index]->getOrderShipment() : null;
            $error    = $dataObject->getData('errors');

            if ($error !== null) {
                $errorResponses[] = $this->errorResponseFactory->create([
                    'data' => [
                        ShipmentResponseInterface::REQUEST_INDEX  => (string) $index,
                        ShipmentResponseInterface::SALES_SHIPMENT => $shipment,
                        ShipmentErrorResponseInterface::ERRORS    => [$error],
                    ],
                ]);
            } else {
                $outboundLabel   = $dataObject->getData('shipping_label_content');
                $returnLabelData = $dataObject->getData('return_label_content');
                $trackingNumber  = $dataObject->getData('tracking_number');

                $labelData = [
                    LabelResponseInterface::REQUEST_INDEX          => (string) $index,
                    LabelResponseInterface::SALES_SHIPMENT         => $shipment,
                    LabelResponseInterface::TRACKING_NUMBER        => $trackingNumber,
                    LabelResponseInterface::SHIPPING_LABEL_CONTENT => $outboundLabel,
                ];

                if ($returnLabelData !== null && $returnLabelData !== '') {
                    $returnDoc = $this->returnDocumentFactory->create([
                        'data' => [
                            ShipmentDocumentInterface::TITLE     => 'Retoure-Paketschein',
                            ShipmentDocumentInterface::MIME_TYPE => 'application/pdf',
                            ShipmentDocumentInterface::LABEL_DATA => base64_encode($returnLabelData),
                            ReturnShipmentDocumentInterface::TRACKING_NUMBER => $trackingNumber,
                        ],
                    ]);
                    $labelData[LabelResponseInterface::DOCUMENTS] = [$returnDoc];

                    try {
                        $combined = $this->labelGenerator
                            ->combineLabelsPdf([$outboundLabel, $returnLabelData])
                            ->render();
                        $labelData[LabelResponseInterface::SHIPPING_LABEL_CONTENT] = $combined;
                    } catch (\Zend_Pdf_Exception $e) {
                        // If combining fails, fall back to outbound-only; return label is still attached as document.
                    }
                }

                $labelResponses[] = $this->labelResponseFactory->create(['data' => $labelData]);
            }
        }

        $this->createResponseProcessor->processResponse($labelResponses, $errorResponses);

        return array_merge($labelResponses, $errorResponses);
    }

    /**
     * Convert ShipIt cancel DataObject results into TrackResponseInterface objects
     * that the Netresearch cancel controller and response processors expect.
     *
     * @param DataObject[]            $shipItResults  Plain DataObjects from ShipItService
     * @param TrackRequestInterface[] $cancelRequests Original requests keyed by track number
     * @return TrackResponseInterface[]
     */
    private function convertShipItCancelResults(array $shipItResults, array $cancelRequests): array
    {
        $responses = [];

        foreach ($shipItResults as $dataObject) {
            $trackNumber    = (string) $dataObject->getData('track_number');
            $cancelRequest  = $cancelRequests[$trackNumber] ?? null;

            $data = [
                TrackResponseInterface::TRACK_NUMBER   => $trackNumber,
                TrackResponseInterface::SALES_SHIPMENT => $cancelRequest ? $cancelRequest->getSalesShipment() : null,
                TrackResponseInterface::SALES_TRACK    => $cancelRequest ? $cancelRequest->getSalesTrack() : null,
            ];

            $error = $dataObject->getData('errors');
            if ($error !== null) {
                $data[TrackErrorResponseInterface::ERRORS] = [$error];
                $responses[] = $this->trackErrorResponseFactory->create(['data' => $data]);
            } else {
                $responses[] = $this->trackResponseFactory->create(['data' => $data]);
            }
        }

        return $responses;
    }
}
