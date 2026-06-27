<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt\Auth;

/**
 * Holds a ShipIt OAuth bearer token and its expiry time.
 */
class AccessToken
{
    /**
     * @var string
     */
    private $token;

    /**
     * Unix timestamp after which the token is considered expired.
     *
     * @var int
     */
    private $expiresAt;

    /**
     * @param string $token
     * @param int    $expiresIn Lifetime in seconds as returned by the token endpoint.
     */
    public function __construct(string $token, int $expiresIn)
    {
        $this->token = $token;
        // Subtract 30 s to avoid using a token that is about to expire mid-request.
        $this->expiresAt = time() + $expiresIn - 30;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }
}
