<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt;

use GlsGroup\Shipping\Model\ShipIt\Exception\ApiException;
use GlsGroup\Shipping\Model\ShipIt\Exception\AuthenticationException;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Shipping\Model\Shipment\Request;
use Psr\Log\LoggerInterface;

/**
 * Performs label creation and cancellation via the ShipIt API.
 *
 * Returns Magento DataObjects so the carrier framework can consume them
 * directly without any additional mapping layer.
 */
class ShipItService
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var RequestMapper
     */
    private $requestMapper;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        HttpClient $httpClient,
        RequestMapper $requestMapper,
        DataObjectFactory $dataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->httpClient        = $httpClient;
        $this->requestMapper     = $requestMapper;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logger            = $logger;
    }

    /**
     * Create shipping labels via the ShipIt API.
     *
     * One API call is made per request. Returns one DataObject per request,
     * each containing either:
     *   - tracking_number + shipping_label_content  (success)
     *   - errors                                    (failure)
     *
     * @param Request[] $requests
     * @param int       $storeId
     * @return DataObject[]
     */
    public function createShipments(array $requests, int $storeId): array
    {
        $results = [];

        foreach ($requests as $request) {
            try {
                $payload = $this->requestMapper->mapRequest($request);
                $this->logger->debug('[ShipIt] createShipments request', ['payload' => $payload]);
                $response = $this->httpClient->post('/rs/shipments', $payload, $storeId);
                $this->logger->debug('[ShipIt] createShipments response', [
                    'track_id' => $response['CreatedShipment']['ParcelData'][0]['TrackID'] ?? null,
                ]);
                $results[] = $this->buildLabelResult($response);
            } catch (AuthenticationException | ApiException $e) {
                $this->logger->error('[ShipIt] createShipments failed', ['error' => $e->getMessage()]);
                $results[] = $this->buildErrorResult($e->getMessage());
            } catch (\Throwable $e) {
                $this->logger->critical('[ShipIt] createShipments unexpected error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[] = $this->buildErrorResult($e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Cancel shipping labels via the ShipIt API.
     *
     * One API call is made per track number. Returns one DataObject per entry,
     * each containing either:
     *   - track_number           (success)
     *   - track_number + errors  (failure)
     *
     * @param string[] $trackNumbers
     * @param int      $storeId
     * @return DataObject[]
     */
    public function cancelShipments(array $trackNumbers, int $storeId): array
    {
        $results = [];

        foreach ($trackNumbers as $trackNumber) {
            try {
                $this->logger->debug('[ShipIt] cancelShipments request', ['trackNumber' => $trackNumber]);
                $response = $this->httpClient->post(
                    '/rs/shipments/cancel/' . rawurlencode($trackNumber),
                    [],
                    $storeId
                );
                $this->logger->debug('[ShipIt] cancelShipments response', ['response' => $response]);
                $results[] = $this->dataObjectFactory->create([
                    'data' => ['track_number' => $trackNumber],
                ]);
            } catch (AuthenticationException | ApiException $e) {
                $this->logger->error('[ShipIt] cancelShipments failed', [
                    'trackNumber' => $trackNumber,
                    'error'       => $e->getMessage(),
                ]);
                $results[] = $this->dataObjectFactory->create([
                    'data' => [
                        'track_number' => $trackNumber,
                        'errors'       => $e->getMessage(),
                    ],
                ]);
            } catch (\Throwable $e) {
                $this->logger->critical('[ShipIt] cancelShipments unexpected error', [
                    'trackNumber' => $trackNumber,
                    'error'       => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                ]);
                $results[] = $this->dataObjectFactory->create([
                    'data' => [
                        'track_number' => $trackNumber,
                        'errors'       => $e->getMessage(),
                    ],
                ]);
            }
        }

        return $results;
    }

    /**
     * Build a successful label result DataObject from a ShipIt CreateParcelsResponse.
     *
     * ShipIt returns one ParcelData entry per shipment unit. The carrier module
     * always creates one unit per request, so index [0] is always the right one.
     * Label bytes are base64-encoded inside PrintData[].Data[].
     *
     * When the ShopReturn service was requested the API returns two PrintData entries:
     *   PrintData[0] — outbound label
     *   PrintData[1] — return label (shop-return label for the customer to use)
     * In that case return_label_content is populated so ShipmentManagement can attach
     * the return document to the LabelResponse and combine the two PDFs.
     */
    private function buildLabelResult(array $response): DataObject
    {
        $parcelData      = $response['CreatedShipment']['ParcelData'][0] ?? [];
        $outboundData    = $response['CreatedShipment']['PrintData'][0] ?? [];
        $returnPrintData = $response['CreatedShipment']['PrintData'][1] ?? null;

        $trackId = $parcelData['TrackID'] ?? '';

        $rawOutbound = $outboundData['Data'] ?? '';
        $labelData   = is_array($rawOutbound)
            ? base64_decode($rawOutbound[0])
            : base64_decode($rawOutbound);

        $data = [
            'tracking_number'        => $trackId,
            'shipping_label_content' => $labelData,
        ];

        if ($returnPrintData !== null) {
            $rawReturn = $returnPrintData['Data'] ?? '';
            $data['return_label_content'] = is_array($rawReturn)
                ? base64_decode($rawReturn[0])
                : base64_decode($rawReturn);
        }

        return $this->dataObjectFactory->create(['data' => $data]);
    }

    private function buildErrorResult(string $message): DataObject
    {
        return $this->dataObjectFactory->create([
            'data' => ['errors' => $message],
        ]);
    }
}
