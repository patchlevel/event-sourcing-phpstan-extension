<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Helper;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;

/** @internal */
final class EventSourcing
{
    public static function isAggregate(ClassReflection $classReflection): bool
    {
        return $classReflection->implementsInterface(AggregateRoot::class);
    }

    public static function isApplyMethod(FunctionReflection|ExtendedMethodReflection $function): bool
    {
        return self::attributes($function->getAttributes(), Apply::class) !== [];
    }

    /** Checks the attributes of a method or property node, the names are already resolved by the name resolver. */
    public static function hasAttribute(ClassMethod|Property $node, string $attribute): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $attribute) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<AttributeReflection> $attributes
     *
     * @return list<AttributeReflection>
     */
    public static function attributes(array $attributes, string $name): array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== $name) {
                continue;
            }

            $result[] = $attribute;
        }

        return $result;
    }
}
