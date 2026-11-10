<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\ValueObject;

use Atomicptr\Symfony\IndexedSearch\Enum\MemberKind;

/**
 * A property or method we read values from
 *
 * @internal
 */
final readonly class Member
{
    /**
     * @param class-string $declaringClass
     */
    public function __construct(
        public string $declaringClass,
        public string $name,
        public MemberKind $kind,
    ) {
    }

    public function label(): string
    {
        return match ($this->kind) {
            MemberKind::Property => "{$this->declaringClass}::\${$this->name}",
            MemberKind::Method => "{$this->declaringClass}::{$this->name}()",
        };
    }
}
