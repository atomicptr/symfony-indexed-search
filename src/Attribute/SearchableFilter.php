<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Attribute;

/**
 * Value you can filter searches by, e.g. #[SearchableFilter('tenant')]
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class SearchableFilter
{
    public function __construct(
        public string $name,
    ) {
    }
}
