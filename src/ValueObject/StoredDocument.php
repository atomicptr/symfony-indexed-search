<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

/**
 * A row already in the document table
 *
 * @internal
 */
final readonly class StoredDocument
{
    public function __construct(
        public int|string $id,
        public string $contentHash,
    ) {
    }
}
