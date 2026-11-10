<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Exception;

use UnexpectedValueException;

/**
 * An entity provided a value that cannot be indexed
 *
 * @api
 */
final class InvalidValueException extends UnexpectedValueException implements IndexedSearchException
{
}
