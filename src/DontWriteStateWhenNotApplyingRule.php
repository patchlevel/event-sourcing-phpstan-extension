<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Unset_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
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
        $targets = $this->writeTargets($node);

        if ($targets === []) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || !$classReflection->implementsInterface(AggregateRoot::class)) {
            return [];
        }

        $function = $scope->getFunction();

        if ($function === null || $this->isApplyMethod($function)) {
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

    /** @return list<PropertyFetch|StaticPropertyFetch> */
    private function writeTargets(Node $node): array
    {
        if (
            $node instanceof Assign
            || $node instanceof AssignOp
            || $node instanceof AssignRef
            || $node instanceof PreInc
            || $node instanceof PreDec
            || $node instanceof PostInc
            || $node instanceof PostDec
        ) {
            return $this->writtenProperties($node->var);
        }

        if ($node instanceof Unset_) {
            $targets = [];

            foreach ($node->vars as $var) {
                foreach ($this->writtenProperties($var) as $target) {
                    $targets[] = $target;
                }
            }

            return $targets;
        }

        return [];
    }

    /** @return list<PropertyFetch|StaticPropertyFetch> */
    private function writtenProperties(Expr $expr): array
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        if ($expr instanceof PropertyFetch || $expr instanceof StaticPropertyFetch) {
            return [$expr];
        }

        if ($expr instanceof List_) {
            $targets = [];

            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }

                foreach ($this->writtenProperties($item->value) as $target) {
                    $targets[] = $target;
                }
            }

            return $targets;
        }

        return [];
    }

    private function isApplyMethod(FunctionReflection|MethodReflection $function): bool
    {
        foreach ($function->getAttributes() as $attribute) {
            if ($attribute->getName() === Apply::class) {
                return true;
            }
        }

        return false;
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
