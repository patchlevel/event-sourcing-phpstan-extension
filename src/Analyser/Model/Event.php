<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class Event
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
    ) {
    }
}
