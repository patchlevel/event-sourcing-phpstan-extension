<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class UserInterface
{
    /**
     * @param list<string> $commands
     * @param list<string> $subscribers
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly array $commands,
        public readonly array $subscribers,
    ) {
    }
}
