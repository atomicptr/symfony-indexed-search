<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Enum;

/**
 * @internal
 */
enum MemberKind
{
    case Property;
    case Method;
}
