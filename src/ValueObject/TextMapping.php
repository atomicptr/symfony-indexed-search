<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use Atomicptr\Symfony\IndexedSearch\Enum\Weight;

/**
 * @internal
 */
final readonly class TextMapping
{
    public function __construct(
        public Member $member,
        public Weight $weight,
    ) {
    }
}
