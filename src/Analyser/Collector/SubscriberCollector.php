<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;

use function array_keys;

/**
 * Collects every subscriber, processor and projector with the events of its #[Subscribe] methods.
 *
 * @phpstan-type SubscriberData array{class: string, type: 'subscriber'|'processor'|'projector', name: string, events: list<string>}
 * @implements Collector<InClassNode, SubscriberData>
 */
final class SubscriberCollector implements Collector
{
    private const TYPES = [
        Subscriber::class => 'subscriber',
        Processor::class => 'processor',
        Projector::class => 'projector',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return SubscriberData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();

        foreach ($classReflection->getAttributes() as $attribute) {
            $type = self::TYPES[$attribute->getName()] ?? null;

            if ($type === null) {
                continue;
            }

            $name = EventSourcing::stringArgument($attribute, 'id');

            if ($name === null) {
                continue;
            }

            return [
                'class' => $classReflection->getName(),
                'type' => $type,
                'name' => $name,
                'events' => $this->subscribedEvents($classReflection),
            ];
        }

        return null;
    }

    /** @return list<string> */
    private function subscribedEvents(ClassReflection $classReflection): array
    {
        $events = [];

        foreach ($classReflection->getNativeReflection()->getMethods() as $method) {
            $attributes = $classReflection->getNativeMethod($method->getName())->getAttributes();

            foreach (EventSourcing::attributes($attributes, Subscribe::class) as $attribute) {
                $event = EventSourcing::stringArgument($attribute, 'eventClass');

                if ($event === null) {
                    continue;
                }

                $events[$event] = true;
            }
        }

        return array_keys($events);
    }
}
