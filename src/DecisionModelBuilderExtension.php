<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\DCB\DecisionModel;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;

use function count;

final class DecisionModelBuilderExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return DecisionModelBuilder::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'build';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        if (count($methodCall->getArgs()) === 0) {
            return null;
        }

        $arg = $methodCall->getArgs()[0]->value;
        $type = $scope->getType($arg);

        if (!$type->isConstantArray()->yes()) {
            return null;
        }

        $resultType = $type->traverse(static function (Type $type) use ($scope) {
            if (!$type->isObject()->yes()) {
                return $type;
            }

            if (!$type->hasMethod('initialState')->yes()) {
                return $type;
            }

            $method = $type->getMethod('initialState', $scope);
            $variants = $method->getVariants();

            $acceptor = $variants[0] ?? null;

            return $acceptor?->getReturnType() ?? $type;
        });

        return new GenericObjectType(
            DecisionModel::class,
            [$resultType],
        );
    }
}
