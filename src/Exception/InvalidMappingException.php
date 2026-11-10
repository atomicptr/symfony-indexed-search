<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Exception;

use LogicException;

/**
 * Invalid searchable attribute configuration on an entity
 *
 * @api
 */
final class InvalidMappingException extends LogicException implements IndexedSearchException
{
}
