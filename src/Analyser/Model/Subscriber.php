<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

final class Subscriber
{
    /**
     * @param list<string> $events   contains "*" if the subscriber subscribes to all events
     * @param list<string> $commands
     */
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly SubscriberType $type,
        public readonly array $events,
        public readonly array $commands,
    ) {
    }
}
