<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Atomicptr\Symfony\IndexedSearch\Enum\TextFormat;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Attribute;

/**
 * Text the entity can be found by
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class SearchableText
{
    public function __construct(
        public Weight $weight = Weight::Normal,
        public TextFormat $format = TextFormat::Plain,
    ) {
    }
}
