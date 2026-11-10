<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable]
class CompositeId
{
    #[ORM\Id]
    #[ORM\Column]
    public int $a = 1;

    #[ORM\Id]
    #[ORM\Column]
    public int $b = 1;

    #[SearchableText]
    public string $text = '';
}
