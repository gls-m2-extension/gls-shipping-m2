<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\ShipIt\Auth\TokenProvider;
use GlsGroup\Shipping\Model\ShipIt\Exception\ApiException;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Thin authenticated HTTP client for the ShipIt REST API.
 *
 * Handles token acquisition, request serialisation, and response validation.
 * A fresh Curl instance is created for every call to avoid header bleed.
 */
class HttpClient
{
    private const BASE_URL_PRODUCTION = 'https://api.gls-group.net/shipit-farm/v1/backend';
    private const BASE_URL_SANDBOX    = 'https://api-sandbox.gls-group.net/shipit-farm/v1/backend';
    private const CONTENT_TYPE        = 'application/glsVersion1+json';

    /**
     * @var TokenProvider
     */
    private $tokenProvider;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TokenProvider $tokenProvider,
        ModuleConfig $moduleConfig,
        ClientFactory $httpClientFactory,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->tokenProvider     = $tokenProvider;
        $this->moduleConfig      = $moduleConfig;
        $this->httpClientFactory = $httpClientFactory;
        $this->json              = $json;
        $this->logger            = $logger;
    }

    /**
     * POST to the given ShipIt API path and return the decoded response body.
     *
     * Pass an empty array for endpoints that take no request body (e.g. cancel by trackID).
     *
     * @param string  $path    API path relative to the base URL (e.g. "/rs/shipments")
     * @param array   $payload Request data; empty array sends no body.
     * @param int     $storeId Magento store ID (selects credentials + sandbox flag)
     * @return array           Decoded response body
     *
     * @throws \GlsGroup\Shipping\Model\ShipIt\Exception\AuthenticationException
     * @throws ApiException
     */
    public function post(string $path, array $payload, int $storeId): array
    {
        $url    = $this->baseUrl($storeId) . $path;
        $token  = $this->tokenProvider->getToken($storeId);
        $body   = $payload ? $this->json->serialize($payload) : '';

        $headers = [
            'Accept'        => self::CONTENT_TYPE,
            'Content-Type'  => self::CONTENT_TYPE,
            'Authorization' => 'Bearer ' . $token,
        ];

        /** @var Curl $client */
        $client = $this->httpClientFactory->create();
        $client->setHeaders($headers);
        $client->post($url, $body);

        $status          = $client->getStatus();
        $responseBody    = $client->getBody();
        $responseHeaders = $client->getHeaders();

        if ($status < 200 || $status >= 300) {
            $this->logger->error('[ShipIt] HTTP error', [
                'context'          => 'POST ' . $path,
                'status'           => $status,
                'request_body'     => $body,
                'response_body'    => $responseBody,
                'response_headers' => $responseHeaders,
            ]);

            $errorDetail = $responseBody ?: ($responseHeaders['message'] ?? '');
            throw new ApiException(
                sprintf('ShipIt API error [POST %s] — HTTP %d: %s', $path, $status, $errorDetail)
            );
        }

        return $this->parseBody($responseBody, 'POST ' . $path);
    }

    private function baseUrl(int $storeId): string
    {
        return $this->moduleConfig->isShipItSandboxMode($storeId)
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_PRODUCTION;
    }

    /**
     * @throws ApiException
     */
    private function parseBody(string $body, string $context): array
    {
        if ($body === '') {
            return [];
        }

        try {
            return $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException(
                sprintf('ShipIt API returned non-JSON response [%s]: %s', $context, $body)
            );
        }
    }
}
