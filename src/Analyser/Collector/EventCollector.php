<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects every event with the name of its #[Event] attribute.
 *
 * @phpstan-type EventData array{class: string, name: string}
 * @implements Collector<InClassNode, EventData>
 */
final class EventCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return EventData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();

        foreach (EventSourcing::attributes($classReflection->getAttributes(), Event::class) as $attribute) {
            $name = EventSourcing::stringArgument($attribute, 'name');

            if ($name === null) {
                continue;
            }

            return [
                'class' => $classReflection->getName(),
                'name' => $name,
            ];
        }

        return null;
    }
}
