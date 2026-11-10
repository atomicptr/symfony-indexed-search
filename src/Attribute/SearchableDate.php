<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Attribute;

/**
 * Date used for sorting by date, date ranges and recency
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class SearchableDate
{
}
