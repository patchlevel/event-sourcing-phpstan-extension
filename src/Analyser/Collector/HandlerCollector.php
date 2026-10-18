<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects the #[Handle] methods of a class, in aggregates as well as in handler services.
 * The class of a handler is the class declaring the method, so it matches the calls collected in its body.
 *
 * @phpstan-type HandlerData array{class: string, handlers: list<array{class: string, method: string, commands: list<string>}>}
 * @implements Collector<InClassNode, HandlerData>
 */
final class HandlerCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return HandlerData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $node->getClassReflection();
        $handlers = [];

        foreach ($classReflection->getNativeReflection()->getMethods() as $method) {
            $methodReflection = $classReflection->getNativeMethod($method->getName());
            $commands = EventSourcing::handledCommands($methodReflection);

            if ($commands === []) {
                continue;
            }

            $handlers[] = [
                'class' => $methodReflection->getDeclaringClass()->getName(),
                'method' => $method->getName(),
                'commands' => $commands,
            ];
        }

        if ($handlers === []) {
            return null;
        }

        return [
            'class' => $classReflection->getName(),
            'handlers' => $handlers,
        ];
    }
}
