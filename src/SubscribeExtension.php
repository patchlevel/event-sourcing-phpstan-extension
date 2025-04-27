<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function assert;

final class SubscribeExtension implements ExpressionTypeResolverExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function getClass(): string
    {
        return '';
    }

    public function isMethodSupported(
        MethodReflection $methodReflection,
        MethodCall $node,
        TypeSpecifierContext $context,
    ): bool {
        $attributes = $methodReflection->getDeclaringClass()->getNativeReflection()->getMethod($methodReflection->getName())->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Subscribe::class) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context,
    ): SpecifiedTypes {
        $attributes = $methodReflection->getDeclaringClass()->getNativeReflection()->getMethod($methodReflection->getName())->getAttributes();
        $class = '';

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Subscribe::class) {
                continue;
            }

            $subscribeAttribute = $attribute->newInstance();
            assert($subscribeAttribute instanceof Subscribe);
            $class = $subscribeAttribute->eventClass;
        }

        $expr = $node->getArgs()[0]->value;

        // Assuming extension implements \PHPStan\Analyser\TypeSpecifierAwareExtension

        return $this->typeSpecifier->create($expr, new ObjectType($class), TypeSpecifierContext::createTruthy());
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    private function isSupported(Expr $expr, Scope $scope): bool
    {
        if (!$expr instanceof MethodCall) {
            return false;
        }

        if (!$scope->isInClass()) {
            return false;
        }

        $attributes = $scope->getClassReflection()->getNativeReflection()->getMethod($expr->name->name)->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Subscribe::class) {
                return true;
            }
        }

        return false;
    }

    public function getType(Expr $expr, Scope $scope): Type|null
    {
        if (!$this->isSupported($expr, $scope)) {
            return null;
        }

        $attributes = $scope->getClassReflection()->getNativeReflection()->getMethod($expr->name->name)->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Subscribe::class) {
                continue;
            }

            $subscribeAttribute = $attribute->newInstance();
            assert($subscribeAttribute instanceof Subscribe);

            return new ObjectType($subscribeAttribute->eventClass);
        }

        return null;
    }
}
