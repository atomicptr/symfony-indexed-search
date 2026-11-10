<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Unsearchable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $secret = '';
}
