<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

use function array_keys;

/**
 * Collects every projection a decision can read, with the events of its #[Apply] methods.
 *
 * @phpstan-type ProjectionData array{class: string, events: list<string>}
 * @implements Collector<InClassNode, ProjectionData>
 */
final class ProjectionCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return ProjectionData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();

        if ($classReflection->isAbstract() || !$classReflection->implementsInterface(Projection::class)) {
            return null;
        }

        $events = [];

        foreach ($classReflection->getNativeReflection()->getMethods() as $method) {
            // the first parameter of an apply method in a projection is the state, the second one the event
            $appliedEvents = EventSourcing::appliedEvents($classReflection->getNativeMethod($method->getName()), 1);

            foreach ($appliedEvents as $event) {
                $events[$event] = true;
            }
        }

        return [
            'class' => $classReflection->getName(),
            'events' => array_keys($events),
        ];
    }
}
