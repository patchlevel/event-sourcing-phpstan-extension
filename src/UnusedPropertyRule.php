<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_key_exists;
use function sprintf;

/** @implements Rule<InClassNode> */
final class UnusedPropertyRule implements Rule
{
    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (
            !$classReflection->implementsInterface(AggregateRoot::class)
            && !$classReflection->implementsInterface(ChildAggregate::class)
        ) {
            return [];
        }

        if ($classReflection->isAbstract()) {
            return [];
        }

        $classNode = $node->getOriginalNode();

        $writtenInApply = [];

        foreach ($classNode->getMethods() as $method) {
            if (!$this->isApplyMethod($method)) {
                continue;
            }

            foreach ($this->writtenPropertyNames($method) as $propertyName) {
                $writtenInApply[$propertyName] = true;
            }
        }

        $errors = [];

        foreach ($classNode->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->props as $propertyItem) {
                if ($propertyItem->default !== null) {
                    continue;
                }

                $propertyName = $propertyItem->name->toString();

                if (array_key_exists($propertyName, $writtenInApply)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Property "%s" of aggregate "%s" is never written in an #[Apply] method and is therefore unused.',
                    $propertyName,
                    $classReflection->getName(),
                ))
                    ->identifier('patchlevel.unusedProperty')
                    ->line($propertyItem->getStartLine())
                    ->tip('Change the state in an #[Apply] method or remove the property.')
                    ->build();
            }
        }

        return $errors;
    }

    private function isApplyMethod(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === Apply::class) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private function writtenPropertyNames(ClassMethod $method): array
    {
        $names = [];

        $writes = $this->nodeFinder->find(
            $method->stmts ?? [],
            static fn (Node $node): bool => $node instanceof Assign
                || $node instanceof AssignOp
                || $node instanceof AssignRef
                || $node instanceof PreInc
                || $node instanceof PreDec
                || $node instanceof PostInc
                || $node instanceof PostDec,
        );

        foreach ($writes as $write) {
            /** @var Assign|AssignOp|AssignRef|PreInc|PreDec|PostInc|PostDec $write */
            foreach ($this->propertyNamesOfTarget($write->var) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return list<string> */
    private function propertyNamesOfTarget(Expr $expr): array
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        if ($expr instanceof PropertyFetch) {
            if (
                $expr->var instanceof Variable
                && $expr->var->name === 'this'
                && $expr->name instanceof Identifier
            ) {
                return [$expr->name->toString()];
            }

            return [];
        }

        if ($expr instanceof List_) {
            $names = [];

            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }

                foreach ($this->propertyNamesOfTarget($item->value) as $name) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        return [];
    }
}
