<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\PHPStan;

use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDate;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableDocuments;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableFilter;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableText;
use Atomicptr\Symfony\IndexedSearch\Attribute\SearchableWhen;
use Override;
use ReflectionMethod;
use ReflectionProperty;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Tells shipmonk/dead-code-detector that the bundle reads members with its attributes, include extension.neon to use it
 *
 * @api
 */
final class SearchableUsageProvider extends ReflectionBasedMemberUsageProvider
{
    private const array ATTRIBUTES = [
        SearchableText::class,
        SearchableFilter::class,
        SearchableDate::class,
        SearchableDocuments::class,
        SearchableWhen::class,
    ];

    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        return $this->usage($method);
    }

    #[Override]
    protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->usage($property);
    }

    private function usage(ReflectionMethod|ReflectionProperty $member): ?VirtualUsageData
    {
        foreach (self::ATTRIBUTES as $attribute) {
            if (count($member->getAttributes($attribute)) > 0) {
                return VirtualUsageData::withNote('Read by indexed search');
            }
        }

        return null;
    }
}
