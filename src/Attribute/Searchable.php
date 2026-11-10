<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Attribute;
use Stringable;
use UnitEnum;

/**
 * Makes a Doctrine entity searchable. Nothing is indexed unless annotated with {@see SearchableText} or {@see SearchableDocuments}
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Searchable
{
    /**
     * @param array<string, string|int|bool|Stringable|UnitEnum|list<string|int|bool|Stringable|UnitEnum>> $filters
     */
    public function __construct(
        public array $filters = [],
    ) {
    }
}
