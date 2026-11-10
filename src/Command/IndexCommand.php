<?php

declare(strict_types = 1);

namespace Atomicptr\Symfony\IndexedSearch\Command;

use Atomicptr\Symfony\IndexedSearch\Service\SearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'indexed-search:index',
    description: 'Rebuild the search index and remove documents of deleted entities or unsearchable classes',
)]
final readonly class IndexCommand
{
    public function __construct(
        private SearchIndexer $indexer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Number of entities loaded per batch')]
        int $batchSize = 1000,
    ): int {
        if ($batchSize < 1) {
            $io->error('--batch-size must be at least 1.');

            return Command::INVALID;
        }

        $processed = $this->indexer->reindex($batchSize, static fn(string $class, int $count) => $io->writeln(
            "Indexed {$count} {$class}",
            SymfonyStyle::VERBOSITY_VERBOSE,
        ));

        $io->success("Indexed {$processed} entities.");

        return Command::SUCCESS;
    }
}
