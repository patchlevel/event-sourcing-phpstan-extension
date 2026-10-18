<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

use JsonSerializable;
use stdClass;

final class Project implements JsonSerializable
{
    /**
     * @param array<string, BoundedContext> $boundedContexts
     * @param array<string, Aggregate>      $aggregates
     * @param array<string, Event>          $events
     * @param array<string, Command>        $commands
     * @param array<string, Subscriber>     $subscribers
     * @param array<string, Projection>     $projections
     * @param array<string, UserInterface>  $userInterfaces
     */
    public function __construct(
        public readonly array $boundedContexts,
        public readonly array $aggregates,
        public readonly array $events,
        public readonly array $commands,
        public readonly array $subscribers,
        public readonly array $projections,
        public readonly array $userInterfaces,
    ) {
    }

    /** @return array<string, array<string, object>|stdClass> */
    public function jsonSerialize(): array
    {
        // the collections are keyed by name or class, so an empty one is still an object and not a list
        return [
            'boundedContexts' => self::map($this->boundedContexts),
            'aggregates' => self::map($this->aggregates),
            'events' => self::map($this->events),
            'commands' => self::map($this->commands),
            'subscribers' => self::map($this->subscribers),
            'projections' => self::map($this->projections),
            'userInterfaces' => self::map($this->userInterfaces),
        ];
    }

    /**
     * @param array<string, object> $map
     *
     * @return array<string, object>|stdClass
     */
    private static function map(array $map): array|stdClass
    {
        return $map === [] ? new stdClass() : $map;
    }
}
