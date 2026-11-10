<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable]
class MethodWithParameter
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;

    #[SearchableText]
    public function text(string $prefix): string
    {
        return $prefix;
    }
}
