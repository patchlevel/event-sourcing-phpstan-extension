<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class Command
{
    /**
     * @param list<string> $events      the events the handlers of the command record or append
     * @param list<string> $projections the projections the handlers of the command read to decide
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $events,
        public readonly array $projections,
    ) {
    }
}
