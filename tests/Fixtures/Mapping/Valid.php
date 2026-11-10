<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Atomicptr\Symfony\IndexedSearch\Attribute\Searchable;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Atomicptr\Symfony\IndexedSearch\Enum\Weight;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[Searchable(filters: ['area' => ['one', 'two', 'one']])]
class Valid
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;

    #[SearchableText(weight: Weight::Low)]
    private string $text = '';

    #[SearchableFilter('a')]
    #[SearchableFilter('b')]
    public string $filter = '';

    public function text(): string
    {
        return $this->text;
    }
}
