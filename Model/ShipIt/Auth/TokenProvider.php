<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt\Auth;

use GlsGroup\Shipping\Model\Config\ModuleConfig;
use GlsGroup\Shipping\Model\ShipIt\Exception\AuthenticationException;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Fetches and caches ShipIt OAuth2 bearer tokens (client credentials flow).
 *
 * Tokens are cached in memory per store for the duration of the PHP process,
 * refreshed automatically when they expire.
 */
class TokenProvider
{
    private const TOKEN_URL_PRODUCTION = 'https://api.gls-group.net/oauth2/v2/token';
    private const TOKEN_URL_SANDBOX    = 'https://api-sandbox.gls-group.net/oauth2/v2/token';

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
     * In-memory token cache, keyed by store ID.
     *
     * @var AccessToken[]
     */
    private $cache = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ModuleConfig $moduleConfig,
        ClientFactory $httpClientFactory,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->moduleConfig      = $moduleConfig;
        $this->httpClientFactory = $httpClientFactory;
        $this->json              = $json;
        $this->logger            = $logger;
    }

    /**
     * Return a valid bearer token for the given store, fetching a new one if needed.
     *
     * @throws AuthenticationException
     */
    public function getToken(int $storeId): string
    {
        if (!isset($this->cache[$storeId]) || $this->cache[$storeId]->isExpired()) {
            $this->cache[$storeId] = $this->fetchToken($storeId);
        }

        return $this->cache[$storeId]->getToken();
    }

    /**
     * @throws AuthenticationException
     */
    private function fetchToken(int $storeId): AccessToken
    {
        $tokenUrl = $this->moduleConfig->isShipItSandboxMode($storeId)
            ? self::TOKEN_URL_SANDBOX
            : self::TOKEN_URL_PRODUCTION;

        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->moduleConfig->getShipItClientId($storeId),
            'client_secret' => $this->moduleConfig->getShipItClientSecret($storeId),
        ]);

        $this->logger->debug('[ShipIt] token request', ['url' => $tokenUrl]);

        $client = $this->httpClientFactory->create();
        $client->setHeaders(['Content-Type' => 'application/x-www-form-urlencoded']);
        $client->post($tokenUrl, $body);

        $status       = $client->getStatus();
        $responseBody = $client->getBody();

        $this->logger->debug('[ShipIt] token response', ['status' => $status]);

        if ($status !== 200) {
            $message = sprintf('ShipIt token request failed (HTTP %d): %s', $status, $responseBody);
            $this->logger->error('[ShipIt] ' . $message);
            throw new AuthenticationException($message);
        }

        try {
            $data = $this->json->unserialize($responseBody);
        } catch (\InvalidArgumentException $e) {
            $message = 'ShipIt token response is not valid JSON: ' . $responseBody;
            $this->logger->error('[ShipIt] ' . $message);
            throw new AuthenticationException($message);
        }

        if (empty($data['access_token'])) {
            $message = 'ShipIt token response missing access_token field.';
            $this->logger->error('[ShipIt] ' . $message);
            throw new AuthenticationException($message);
        }

        return new AccessToken($data['access_token'], (int) ($data['expires_in'] ?? 3600));
    }
}
