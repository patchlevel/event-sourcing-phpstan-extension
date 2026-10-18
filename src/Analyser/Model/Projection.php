<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class Projection
{
    /** @param list<string> $events */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $events,
    ) {
    }
}
