<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\Mapping;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ValidChild extends Valid
{
}
