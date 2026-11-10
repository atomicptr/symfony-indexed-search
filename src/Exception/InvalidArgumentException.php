<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Invalid argument passed to the search or indexing API
 *
 * @api
 */
final class InvalidArgumentException extends BaseInvalidArgumentException implements IndexedSearchException
{
}
