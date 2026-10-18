<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use Patchlevel\EventSourcingPHPStanExtension\Helper\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

use function sprintf;

/** @implements Rule<Node> */
final class DontWriteStateWhenNotApplyingRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $targets = [];

        foreach (PropertyWrites::targets($node) as $target) {
            foreach (PropertyWrites::properties($target) as $property) {
                $targets[] = $property;
            }
        }

        if ($targets === []) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || !EventSourcing::isAggregate($classReflection)) {
            return [];
        }

        $function = $scope->getFunction();

        if ($function === null || EventSourcing::isApplyMethod($function)) {
            return [];
        }

        $errors = [];

        foreach ($targets as $target) {
            if (!$this->isAggregateState($target, $scope)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Aggregate state property "%s" should only be written in an #[Apply] method, but is written in "%s::%s()".',
                $this->propertyName($target),
                $classReflection->getName(),
                $function->getName(),
            ))
                ->identifier('patchlevel.noStateWriteWhenNotApplying')
                ->tip('Record an event instead and change the state in an #[Apply] method.')
                ->build();
        }

        return $errors;
    }

    private function isAggregateState(PropertyFetch|StaticPropertyFetch $target, Scope $scope): bool
    {
        $aggregateType = new ObjectType(AggregateRoot::class);

        if ($target instanceof PropertyFetch) {
            return $aggregateType->isSuperTypeOf($scope->getType($target->var))->yes();
        }

        if (!$target->class instanceof Name) {
            return false;
        }

        return $aggregateType->isSuperTypeOf(new ObjectType($scope->resolveName($target->class)))->yes();
    }

    private function propertyName(PropertyFetch|StaticPropertyFetch $target): string
    {
        if ($target->name instanceof Identifier) {
            return $target->name->toString();
        }

        return '{expression}';
    }
}
