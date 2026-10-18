<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Helper;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionReflection;

use function array_keys;
use function count;

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
     * The command classes a #[Handle] method handles: the explicit command class of the attribute,
     * otherwise the type of the first parameter, like the HandlerFinder of the library resolves them.
     *
     * @return list<string>
     */
    public static function handledCommands(ExtendedMethodReflection $method): array
    {
        $commands = [];

        foreach (self::attributes($method->getAttributes(), Handle::class) as $attribute) {
            $commandClass = self::stringArgument($attribute, 'commandClass');

            if ($commandClass !== null) {
                $commands[$commandClass] = true;

                continue;
            }

            foreach (self::parameterClasses($method, 0) as $class) {
                $commands[$class] = true;
            }
        }

        return array_keys($commands);
    }

    /**
     * The event classes an #[Apply] method applies: the explicit event class of the attribute,
     * otherwise the type of the event parameter.
     *
     * @return list<string>
     */
    public static function appliedEvents(ExtendedMethodReflection $method, int $eventParameter): array
    {
        $events = [];

        foreach (self::attributes($method->getAttributes(), Apply::class) as $attribute) {
            $eventClass = self::stringArgument($attribute, 'eventClass');

            if ($eventClass !== null) {
                $events[$eventClass] = true;

                continue;
            }

            foreach (self::parameterClasses($method, $eventParameter) as $class) {
                $events[$class] = true;
            }
        }

        return array_keys($events);
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

    /** The value of a constant string argument of the attribute, null if it is missing or not a single constant string. */
    public static function stringArgument(AttributeReflection $attribute, string $name): string|null
    {
        $argument = $attribute->getArgumentTypes()[$name] ?? null;

        if ($argument === null) {
            return null;
        }

        $constantStrings = $argument->getConstantStrings();

        if (count($constantStrings) !== 1) {
            return null;
        }

        return $constantStrings[0]->getValue();
    }

    /** @return list<string> */
    private static function parameterClasses(ExtendedMethodReflection $method, int $position): array
    {
        $parameter = ($method->getVariants()[0] ?? null)?->getParameters()[$position] ?? null;

        if ($parameter === null) {
            return [];
        }

        return $parameter->getNativeType()->getObjectClassNames();
    }
}
