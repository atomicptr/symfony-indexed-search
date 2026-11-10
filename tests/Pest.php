<?php

declare(strict_types = 1);

use Atomicptr\Symfony\IndexedSearch\Tests\Fixtures\TestApp;
use Atomicptr\Symfony\IndexedSearch\ValueObject\SearchHit;
use Symfony\Component\Filesystem\Filesystem;

new Filesystem()->remove(dirname(__DIR__) . '/var/cache/test');

pest()
    ->in('Functional')
    ->beforeEach(function (): void {
        $this->app = TestApp::boot();
    })
    ->afterEach(function (): void {
        $this->app->shutdown();
    });

/**
 * @param array<string, mixed> $searchConfig
 */
function bootApp(array $searchConfig = []): TestApp
{
    return TestApp::boot($searchConfig);
}

/**
 * @param list<SearchHit> $hits
 *
 * @return list<string>
 */
function ids(array $hits): array
{
    return array_map(static fn(SearchHit $hit): string => $hit->variant === ''
        ? $hit->entityId
        : "{$hit->entityId}:{$hit->variant}", $hits);
}
