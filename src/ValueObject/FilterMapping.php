<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

/**
 * @internal
 */
final readonly class FilterMapping
{
    public function __construct(
        public string $name,
        public Member $member,
    ) {
    }
}
