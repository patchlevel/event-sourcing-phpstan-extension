<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser;

use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\AggregateCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\CallCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\ControllerCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\EventCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\HandlerCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\ProjectionCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector\SubscriberCollector;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Aggregate;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\BoundedContext;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Command;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Event;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Project;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Projection;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Subscriber;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\SubscriberType;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\UserInterface;
use PHPStan\Collectors\CollectedData;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function ksort;
use function preg_match;
use function sort;
use function sprintf;
use function strrpos;
use function substr;

/**
 * Builds the model of the domain from the data of the collectors.
 *
 * @phpstan-import-type AggregateData from AggregateCollector
 * @phpstan-import-type CallData from CallCollector
 * @phpstan-import-type ControllerData from ControllerCollector
 * @phpstan-import-type EventData from EventCollector
 * @phpstan-import-type HandlerData from HandlerCollector
 * @phpstan-import-type ProjectionData from ProjectionCollector
 * @phpstan-import-type SubscriberData from SubscriberCollector
 */
final class ProjectFactory
{
    /** @var array<string, list<CallData>> */
    private array $callsByMethod = [];

    /** @var array<string, list<CallData>> */
    private array $callsByClass = [];

    /** @param list<CollectedData> $collectedData */
    public function create(array $collectedData): Project
    {
        $data = $this->groupByCollector($collectedData);

        $this->callsByMethod = [];
        $this->callsByClass = [];

        /** @var list<CallData> $callData */
        $callData = $data[CallCollector::class] ?? [];

        foreach ($callData as $call) {
            $this->callsByMethod[self::methodKey($call['class'], $call['method'])][] = $call;
            $this->callsByClass[$call['class']][] = $call;
        }

        /** @var list<EventData> $eventData */
        $eventData = $data[EventCollector::class] ?? [];
        /** @var list<AggregateData> $aggregateData */
        $aggregateData = $data[AggregateCollector::class] ?? [];
        /** @var list<HandlerData> $handlerData */
        $handlerData = $data[HandlerCollector::class] ?? [];
        /** @var list<SubscriberData> $subscriberData */
        $subscriberData = $data[SubscriberCollector::class] ?? [];
        /** @var list<ProjectionData> $projectionData */
        $projectionData = $data[ProjectionCollector::class] ?? [];
        /** @var list<ControllerData> $controllerData */
        $controllerData = $data[ControllerCollector::class] ?? [];

        $aggregates = $this->aggregates($aggregateData, $handlerData);
        $subscribers = $this->subscribers($subscriberData);
        $projections = $this->projections($projectionData);
        $userInterfaces = $this->userInterfaces($controllerData);
        $commands = $this->commands($handlerData, $subscribers, $userInterfaces);
        $events = $this->events($eventData, $aggregates, $commands, $subscribers, $projections);

        return new Project(
            $this->boundedContexts($aggregates, $events, $commands, $subscribers, $projections, $userInterfaces),
            $aggregates,
            $events,
            $commands,
            $subscribers,
            $projections,
            $userInterfaces,
        );
    }

    /**
     * The collected data holds the rows of one collector per file.
     *
     * @param list<CollectedData> $collectedData
     *
     * @return array<string, list<mixed>>
     */
    private function groupByCollector(array $collectedData): array
    {
        $result = [];

        foreach ($collectedData as $dataOfFile) {
            /** @var list<mixed> $rows */
            $rows = $dataOfFile->getData();

            foreach ($rows as $row) {
                $result[$dataOfFile->getCollectorType()][] = $row;
            }
        }

        return $result;
    }

    /**
     * @param list<AggregateData> $aggregateData
     * @param list<HandlerData>   $handlerData
     *
     * @return array<string, Aggregate>
     */
    private function aggregates(array $aggregateData, array $handlerData): array
    {
        $result = [];

        foreach ($aggregateData as $data) {
            $commands = [];

            foreach ($handlerData as $handlers) {
                if ($handlers['class'] !== $data['class']) {
                    continue;
                }

                foreach ($handlers['handlers'] as $handler) {
                    $commands = array_merge($commands, $handler['commands']);
                }
            }

            $result[$data['class']] = new Aggregate(
                $data['name'],
                $data['class'],
                $this->targetsInClass($data['class'], 'record'),
                self::sorted($commands),
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * @param list<HandlerData>            $handlerData
     * @param array<string, Subscriber>    $subscribers
     * @param array<string, UserInterface> $userInterfaces
     *
     * @return array<string, Command>
     */
    private function commands(array $handlerData, array $subscribers, array $userInterfaces): array
    {
        /** @var array<string, array{events: list<string>, projections: list<string>}> $commands */
        $commands = [];

        foreach ($handlerData as $handlers) {
            foreach ($handlers['handlers'] as $handler) {
                $method = self::methodKey($handler['class'], $handler['method']);

                foreach ($handler['commands'] as $command) {
                    $commands[$command] = [
                        'events' => array_merge($commands[$command]['events'] ?? [], $this->resolve($method, 'record')),
                        'projections' => array_merge($commands[$command]['projections'] ?? [], $this->resolve($method, 'read')),
                    ];
                }
            }
        }

        $dispatched = [];

        foreach ($subscribers as $subscriber) {
            $dispatched = array_merge($dispatched, $subscriber->commands);
        }

        foreach ($userInterfaces as $userInterface) {
            $dispatched = array_merge($dispatched, $userInterface->commands);
        }

        foreach ($dispatched as $command) {
            $commands[$command] ??= ['events' => [], 'projections' => []];
        }

        $result = [];

        foreach ($commands as $class => $command) {
            $result[$class] = new Command(
                self::shortName($class),
                $class,
                self::sorted($command['events']),
                self::sorted($command['projections']),
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * Every event of the collectors, plus the events that are recorded or subscribed to but are not analysed.
     *
     * @param list<EventData>           $eventData
     * @param array<string, Aggregate>  $aggregates
     * @param array<string, Command>    $commands
     * @param array<string, Subscriber> $subscribers
     * @param array<string, Projection> $projections
     *
     * @return array<string, Event>
     */
    private function events(
        array $eventData,
        array $aggregates,
        array $commands,
        array $subscribers,
        array $projections,
    ): array {
        $result = [];

        foreach ($eventData as $data) {
            $result[$data['class']] = new Event($data['name'], $data['class']);
        }

        $referenced = [];

        foreach ([...$aggregates, ...$commands, ...$subscribers, ...$projections] as $element) {
            $referenced = array_merge($referenced, $element->events);
        }

        foreach ($referenced as $class) {
            if ($class === '*' || array_key_exists($class, $result)) {
                continue;
            }

            $result[$class] = new Event(self::shortName($class), $class);
        }

        ksort($result);

        return $result;
    }

    /**
     * @param list<SubscriberData> $subscriberData
     *
     * @return array<string, Subscriber>
     */
    private function subscribers(array $subscriberData): array
    {
        $result = [];

        foreach ($subscriberData as $data) {
            $result[$data['class']] = new Subscriber(
                $data['name'],
                $data['class'],
                SubscriberType::from($data['type']),
                self::sorted($data['events']),
                $this->targetsInClass($data['class'], 'dispatch'),
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * @param list<ProjectionData> $projectionData
     *
     * @return array<string, Projection>
     */
    private function projections(array $projectionData): array
    {
        $result = [];

        foreach ($projectionData as $data) {
            $result[$data['class']] = new Projection(
                self::shortName($data['class']),
                $data['class'],
                self::sorted($data['events']),
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * Only controllers that dispatch commands or access subscribers are part of the domain.
     *
     * @param list<ControllerData> $controllerData
     *
     * @return array<string, UserInterface>
     */
    private function userInterfaces(array $controllerData): array
    {
        $result = [];

        foreach ($controllerData as $data) {
            $commands = $this->targetsInClass($data['class'], 'dispatch');
            $subscribers = $this->targetsInClass($data['class'], 'access');

            if ($commands === [] && $subscribers === []) {
                continue;
            }

            $result[$data['class']] = new UserInterface(
                self::shortName($data['class']),
                $data['class'],
                $commands,
                $subscribers,
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * @param array<string, Aggregate>     $aggregates
     * @param array<string, Event>         $events
     * @param array<string, Command>       $commands
     * @param array<string, Subscriber>    $subscribers
     * @param array<string, Projection>    $projections
     * @param array<string, UserInterface> $userInterfaces
     *
     * @return array<string, BoundedContext>
     */
    private function boundedContexts(
        array $aggregates,
        array $events,
        array $commands,
        array $subscribers,
        array $projections,
        array $userInterfaces,
    ): array {
        $groups = [
            'aggregates' => array_keys($aggregates),
            'events' => array_keys($events),
            'commands' => array_keys($commands),
            'subscribers' => array_keys($subscribers),
            'projections' => array_keys($projections),
            'userInterfaces' => array_keys($userInterfaces),
        ];

        /** @var array<string, array<string, list<string>>> $contexts */
        $contexts = [];

        foreach ($groups as $group => $classes) {
            foreach ($classes as $class) {
                $context = self::boundedContext($class);

                if ($context === null) {
                    continue;
                }

                $contexts[$context][$group][] = $class;
            }
        }

        $result = [];

        foreach ($contexts as $name => $context) {
            $result[$name] = new BoundedContext(
                $name,
                $context['aggregates'] ?? [],
                $context['events'] ?? [],
                $context['commands'] ?? [],
                $context['subscribers'] ?? [],
                $context['projections'] ?? [],
                $context['userInterfaces'] ?? [],
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * The targets of the given kind in the method and in every method of the own class it calls.
     *
     * @param 'record'|'read'|'dispatch'|'access' $kind
     * @param array<string, true>                 $visited
     *
     * @return list<string>
     */
    private function resolve(string $method, string $kind, array &$visited = []): array
    {
        if (array_key_exists($method, $visited)) {
            return [];
        }

        $visited[$method] = true;
        $targets = [];

        foreach ($this->callsByMethod[$method] ?? [] as $call) {
            if ($call['kind'] === $kind) {
                $targets = array_merge($targets, $call['targets']);

                continue;
            }

            if ($call['kind'] !== 'call') {
                continue;
            }

            foreach ($call['targets'] as $calledMethod) {
                $targets = array_merge($targets, $this->resolve($calledMethod, $kind, $visited));
            }
        }

        return $targets;
    }

    /**
     * The targets of the given kind in all methods of the class.
     *
     * @param 'record'|'read'|'dispatch'|'access' $kind
     *
     * @return list<string>
     */
    private function targetsInClass(string $class, string $kind): array
    {
        $targets = [];

        foreach ($this->callsByClass[$class] ?? [] as $call) {
            if ($call['kind'] !== $kind) {
                continue;
            }

            $targets = array_merge($targets, $call['targets']);
        }

        return self::sorted($targets);
    }

    /**
     * The bounded context is the namespace segment right before a Domain, Application or Infrastructure layer.
     */
    private static function boundedContext(string $class): string|null
    {
        if (preg_match('#(?:^|\\\\)([^\\\\]+)\\\\(?:Domain|Application|Infrastructure)\\\\#', $class, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        if ($position === false) {
            return $class;
        }

        return substr($class, $position + 1);
    }

    private static function methodKey(string $class, string $method): string
    {
        return sprintf('%s::%s', $class, $method);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
