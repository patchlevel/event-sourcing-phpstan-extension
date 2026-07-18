<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\ChildAggregate as ChildAggregateAttribute;
use Patchlevel\EventSourcing\Attribute\Id;
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
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_key_exists;
use function spl_object_id;
use function sprintf;

/** @implements Rule<InClassNode> */
final class WriteOnlyPropertyRule implements Rule
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
        $read = [];

        foreach ($classNode->getMethods() as $method) {
            $writeTargets = $this->writeTargets($method);

            if ($this->isApplyMethod($method)) {
                foreach ($writeTargets as $writeTarget) {
                    foreach ($this->basePropertyFetches($writeTarget) as $propertyFetch) {
                        $propertyName = $this->ownPropertyName($propertyFetch);

                        if ($propertyName === null) {
                            continue;
                        }

                        $writtenInApply[$propertyName] = true;
                    }
                }
            }

            foreach ($this->readPropertyNames($method, $writeTargets) as $propertyName) {
                $read[$propertyName] = true;
            }
        }

        $errors = [];

        foreach ($classNode->getProperties() as $property) {
            if ($property->isStatic() || !$property->isPrivate()) {
                continue;
            }

            if ($this->isLibraryReadProperty($property)) {
                continue;
            }

            foreach ($property->props as $propertyItem) {
                $propertyName = $propertyItem->name->toString();

                if (!array_key_exists($propertyName, $writtenInApply)) {
                    continue;
                }

                if (array_key_exists($propertyName, $read)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Property "%s" of aggregate "%s" is written in an #[Apply] method but never read, so it is not used to check any invariants.',
                    $propertyName,
                    $classReflection->getName(),
                ))
                    ->identifier('patchlevel.writeOnlyProperty')
                    ->line($propertyItem->getStartLine())
                    ->tip('Use the property to check invariants or remove it. State that only exists for reading belongs in a projection.')
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

    private function isLibraryReadProperty(Property $property): bool
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === Id::class || $attr->name->toString() === ChildAggregateAttribute::class) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Collects the expressions that are written to in the method: assignment
     * targets, operands of increment and decrement, and unset() arguments.
     *
     * @return list<Expr>
     */
    private function writeTargets(ClassMethod $method): array
    {
        $targets = [];

        /** @var list<Assign|AssignOp|AssignRef|PreInc|PreDec|PostInc|PostDec|Unset_> $writes */
        $writes = $this->nodeFinder->find(
            $method->stmts ?? [],
            static fn (Node $node): bool => $node instanceof Assign
                || $node instanceof AssignOp
                || $node instanceof AssignRef
                || $node instanceof PreInc
                || $node instanceof PreDec
                || $node instanceof PostInc
                || $node instanceof PostDec
                || $node instanceof Unset_,
        );

        foreach ($writes as $write) {
            if ($write instanceof Unset_) {
                foreach ($write->vars as $var) {
                    $targets[] = $var;
                }

                continue;
            }

            $targets[] = $write->var;
        }

        return $targets;
    }

    /**
     * Collects the names of all properties the method reads: every $this
     * property fetch that is not itself the target of a write.
     *
     * @param list<Expr> $writeTargets
     *
     * @return list<string>
     */
    private function readPropertyNames(ClassMethod $method, array $writeTargets): array
    {
        $writeBaseIds = [];

        foreach ($writeTargets as $writeTarget) {
            foreach ($this->basePropertyFetches($writeTarget) as $propertyFetch) {
                $writeBaseIds[spl_object_id($propertyFetch)] = true;
            }
        }

        $names = [];

        $propertyFetches = $this->nodeFinder->findInstanceOf($method->stmts ?? [], PropertyFetch::class);

        foreach ($propertyFetches as $propertyFetch) {
            if (array_key_exists(spl_object_id($propertyFetch), $writeBaseIds)) {
                continue;
            }

            $propertyName = $this->ownPropertyName($propertyFetch);

            if ($propertyName === null) {
                continue;
            }

            $names[] = $propertyName;
        }

        return $names;
    }

    /** @return list<PropertyFetch> */
    private function basePropertyFetches(Expr $expr): array
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        if ($expr instanceof PropertyFetch) {
            return [$expr];
        }

        if ($expr instanceof List_) {
            $fetches = [];

            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }

                foreach ($this->basePropertyFetches($item->value) as $fetch) {
                    $fetches[] = $fetch;
                }
            }

            return $fetches;
        }

        return [];
    }

    private function ownPropertyName(PropertyFetch $propertyFetch): string|null
    {
        if (
            !$propertyFetch->var instanceof Variable
            || $propertyFetch->var->name !== 'this'
            || !$propertyFetch->name instanceof Identifier
        ) {
            return null;
        }

        return $propertyFetch->name->toString();
    }
}
