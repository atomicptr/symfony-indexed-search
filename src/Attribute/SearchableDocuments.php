<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Attribute;

use Attribute;

/**
 * Method returning your own Document objects instead of using the other attributes, e.g. one per translation
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class SearchableDocuments
{
}
