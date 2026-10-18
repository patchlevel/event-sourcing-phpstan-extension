<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Collector;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Type\Type;

use function sprintf;

/**
 * Collects the calls inside of methods that matter for the model of the domain:
 *
 * - record: events recorded with recordThat() or appended with Decision::append()
 * - read: projections read with Decision::read()
 * - dispatch: commands dispatched through a command bus
 * - access: methods called on a subscriber, processor or projector
 * - call: methods called on the own class, so effects of helper methods can be resolved
 *
 * @phpstan-type CallData array{class: string, method: string, kind: 'record'|'read'|'dispatch'|'access'|'call', targets: list<string>}
 * @implements Collector<CallLike, CallData>
 */
final class CallCollector implements Collector
{
    private const MESSAGE_BUS = 'Symfony\Component\Messenger\MessageBusInterface';
    private const SUBSCRIBER_ATTRIBUTES = [Subscriber::class, Processor::class, Projector::class];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /** @return CallData|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        if (!$node instanceof MethodCall && !$node instanceof NullsafeMethodCall && !$node instanceof StaticCall) {
            return null;
        }

        if ($node->isFirstClassCallable() || !$node->name instanceof Identifier) {
            return null;
        }

        $caller = $scope->getFunction();
        $callerClass = $scope->getClassReflection();

        if (!$caller instanceof ExtendedMethodReflection || $callerClass === null) {
            return null;
        }

        $method = $this->calledMethod($node, $node->name->toString(), $scope);

        if ($method === null) {
            return null;
        }

        $kindAndTargets = $this->kindAndTargets($node, $method, $callerClass, $scope);

        if ($kindAndTargets === null) {
            return null;
        }

        return [
            'class' => $caller->getDeclaringClass()->getName(),
            'method' => $caller->getName(),
            'kind' => $kindAndTargets[0],
            'targets' => $kindAndTargets[1],
        ];
    }

    /** @return array{'record'|'read'|'dispatch'|'access'|'call', list<string>}|null */
    private function kindAndTargets(
        MethodCall|NullsafeMethodCall|StaticCall $node,
        ExtendedMethodReflection $method,
        ClassReflection $callerClass,
        Scope $scope,
    ): array|null {
        $declaringClass = $method->getDeclaringClass();
        $name = $method->getName();
        $argument = $this->firstArgumentType($node, $scope);

        if ($name === 'recordThat' && EventSourcing::isAggregate($declaringClass)) {
            return $argument === null ? null : ['record', $this->objectClasses($argument)];
        }

        if ($declaringClass->is(Decision::class)) {
            if ($argument === null) {
                return null;
            }

            return match ($name) {
                'append' => ['record', $this->objectClasses($argument->getIterableValueType())],
                'read' => ['read', $this->objectClasses($argument->isObject()->yes() ? $argument : $argument->getIterableValueType())],
                default => null,
            };
        }

        if ($name === 'dispatch' && ($declaringClass->is(CommandBus::class) || $declaringClass->is(self::MESSAGE_BUS))) {
            return $argument === null ? null : ['dispatch', $this->objectClasses($argument)];
        }

        foreach (self::SUBSCRIBER_ATTRIBUTES as $subscriberAttribute) {
            if (EventSourcing::attributes($declaringClass->getAttributes(), $subscriberAttribute) !== []) {
                return ['access', [$declaringClass->getName()]];
            }
        }

        if ($callerClass->is($declaringClass->getName())) {
            return ['call', [sprintf('%s::%s', $declaringClass->getName(), $name)]];
        }

        return null;
    }

    private function calledMethod(
        MethodCall|NullsafeMethodCall|StaticCall $node,
        string $name,
        Scope $scope,
    ): ExtendedMethodReflection|null {
        if ($node instanceof StaticCall) {
            if (!$node->class instanceof Name) {
                return null;
            }

            return $scope->getMethodReflection($scope->resolveTypeByName($node->class), $name);
        }

        return $scope->getMethodReflection($scope->getType($node->var), $name);
    }

    private function firstArgumentType(MethodCall|NullsafeMethodCall|StaticCall $node, Scope $scope): Type|null
    {
        $argument = $node->getArgs()[0] ?? null;

        if ($argument === null) {
            return null;
        }

        return $scope->getType($argument->value);
    }

    /** @return list<string> */
    private function objectClasses(Type $type): array
    {
        return $type->getObjectClassNames();
    }
}
