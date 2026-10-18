<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class Aggregate
{
    /**
     * @param list<string> $events
     * @param list<string> $commands
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $events,
        public readonly array $commands,
    ) {
    }
}
