<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Helper;

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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;

use function array_values;

/** @internal */
final class PropertyWrites
{
    /**
     * The expressions the node writes to: assignment targets, operands of increment and decrement
     * and, if wanted, unset() arguments.
     *
     * @return list<Expr>
     */
    public static function targets(Node $node, bool $withUnset = true): array
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
            return [$node->var];
        }

        if ($withUnset && $node instanceof Unset_) {
            return array_values($node->vars);
        }

        return [];
    }

    /**
     * Collects the write targets of all nodes in the given statements.
     *
     * @param array<Node> $stmts
     *
     * @return list<Expr>
     */
    public static function findTargets(array $stmts, bool $withUnset = true): array
    {
        $targets = [];

        $writes = (new NodeFinder())->find(
            $stmts,
            static fn (Node $node): bool => self::targets($node, $withUnset) !== [],
        );

        foreach ($writes as $write) {
            foreach (self::targets($write, $withUnset) as $target) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * The properties a write target changes, also through array dimensions and list() destructuring.
     *
     * @return list<PropertyFetch|StaticPropertyFetch>
     */
    public static function properties(Expr $target): array
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        if ($target instanceof PropertyFetch || $target instanceof StaticPropertyFetch) {
            return [$target];
        }

        if (!$target instanceof List_) {
            return [];
        }

        $properties = [];

        foreach ($target->items as $item) {
            if ($item === null) {
                continue;
            }

            foreach (self::properties($item->value) as $property) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /** The name of the property if it is fetched from $this, otherwise null. */
    public static function ownPropertyName(PropertyFetch|StaticPropertyFetch $propertyFetch): string|null
    {
        if (
            !$propertyFetch instanceof PropertyFetch
            || !$propertyFetch->var instanceof Variable
            || $propertyFetch->var->name !== 'this'
            || !$propertyFetch->name instanceof Identifier
        ) {
            return null;
        }

        return $propertyFetch->name->toString();
    }
}
