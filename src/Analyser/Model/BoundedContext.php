<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class BoundedContext
{
    /**
     * @param list<string> $aggregates
     * @param list<string> $events
     * @param list<string> $commands
     * @param list<string> $subscribers
     * @param list<string> $projections
     * @param list<string> $userInterfaces
     */
    public function __construct(
        public readonly string $name,
        public readonly array $aggregates,
        public readonly array $events,
        public readonly array $commands,
        public readonly array $subscribers,
        public readonly array $projections,
        public readonly array $userInterfaces,
    ) {
    }
}
