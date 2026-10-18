<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Audit;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\RunMode;

/** outside of a layered namespace, so it belongs to no bounded context */
#[Subscriber('audit', RunMode::FromNow)]
final class AuditSubscriber
{
    /** @var list<object> */
    private array $events = [];

    /** @return list<object> */
    public function events(): array
    {
        return $this->events;
    }

    #[Subscribe(Subscribe::ALL)]
    public function onEvent(Message $message): void
    {
        $this->events[] = $message->event();
    }
}
