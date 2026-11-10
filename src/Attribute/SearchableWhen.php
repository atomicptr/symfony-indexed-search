<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Attribute;

/**
 * Boolean property or method deciding if the entity is indexed at all, false removes it from the index
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class SearchableWhen
{
}
