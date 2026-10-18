<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects every aggregate root with the name of its #[Aggregate] attribute.
 *
 * @phpstan-type AggregateData array{class: string, name: string}
 * @implements Collector<InClassNode, AggregateData>
 */
final class AggregateCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return AggregateData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();

        if (!EventSourcing::isAggregate($classReflection)) {
            return null;
        }

        foreach (EventSourcing::attributes($classReflection->getAttributes(), Aggregate::class) as $attribute) {
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
