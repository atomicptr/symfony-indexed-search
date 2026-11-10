<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable]
class InvalidFilterName
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;

    #[SearchableText]
    #[SearchableFilter('Lang Code')]
    public string $text = '';
}
