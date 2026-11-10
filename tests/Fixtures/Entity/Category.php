<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity;

enum Category: string
{
    case Shoes = 'shoes';
    case Shirts = 'shirts';
}
