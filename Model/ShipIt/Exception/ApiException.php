<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace GlsGroup\Shipping\Model\ShipIt\Exception;

use RuntimeException;

/**
 * Thrown when a ShipIt API call returns a non-2xx response.
 */
class ApiException extends RuntimeException
{
}
