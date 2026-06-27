<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt\Exception;

use RuntimeException;

/**
 * Thrown when the ShipIt OAuth token request fails.
 */
class AuthenticationException extends RuntimeException
{
}
