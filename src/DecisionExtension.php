<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\Projection\Projection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

use function count;

final class DecisionExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Decision::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'read';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        if (count($methodCall->getArgs()) === 0) {
            return null;
        }

        $type = $scope->getType($methodCall->getArgs()[0]->value);

        if ($type->isObject()->yes()) {
            return self::stateType($type, $scope);
        }

        if (!$type->isConstantArray()->yes()) {
            return null;
        }

        return $type->traverse(static function (Type $type) use ($scope): Type {
            if (!$type->isObject()->yes()) {
                return $type;
            }

            return self::stateType($type, $scope) ?? $type;
        });
    }

    /**
     * The state type of the projection: its generic type if declared, otherwise the return type of initialState().
     */
    private static function stateType(Type $projection, Scope $scope): Type|null
    {
        $templateType = $projection->getTemplateType(Projection::class, 'S');

        if (!$templateType instanceof MixedType) {
            return $templateType;
        }

        if (!$projection->hasMethod('initialState')->yes()) {
            return null;
        }

        $variants = $projection->getMethod('initialState', $scope)->getVariants();

        return ($variants[0] ?? null)?->getReturnType();
    }
}
