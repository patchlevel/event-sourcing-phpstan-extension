<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects the Symfony controllers, marked with #[AsController] or extending the AbstractController.
 *
 * @phpstan-type ControllerData array{class: string}
 * @implements Collector<InClassNode, ControllerData>
 */
final class ControllerCollector implements Collector
{
    private const AS_CONTROLLER = 'Symfony\Component\HttpKernel\Attribute\AsController';
    private const ABSTRACT_CONTROLLER = 'Symfony\Bundle\FrameworkBundle\Controller\AbstractController';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return ControllerData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();

        if (
            EventSourcing::attributes($classReflection->getAttributes(), self::AS_CONTROLLER) === []
            && !$classReflection->is(self::ABSTRACT_CONTROLLER)
        ) {
            return null;
        }

        return ['class' => $classReflection->getName()];
    }
}
