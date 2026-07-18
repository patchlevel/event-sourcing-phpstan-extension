<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

final class EventCollector
{
    /** @var list<object> */
    private array $events = [];

    public function recordThat(object $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<object> */
    public function events(): array
    {
        return $this->events;
    }
}
