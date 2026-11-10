<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable]
class NothingIndexed
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;

    #[SearchableFilter('tenant')]
    public int $tenant = 1;
}
