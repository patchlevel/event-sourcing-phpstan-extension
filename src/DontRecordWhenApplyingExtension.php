<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Apply;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;

use function sprintf;

final class DontRecordWhenApplyingExtension implements RestrictedMethodUsageExtension
{
    public function isRestrictedMethodUsage(
        ExtendedMethodReflection $methodReflection,
        Scope $scope,
    ): RestrictedUsage|null {
        if ($methodReflection->getName() !== 'recordThat' && !$methodReflection->getDeclaringClass()->implementsInterface(AggregateRoot::class)) {
            return null;
        }

        $inFunction = $scope->getFunction();

        if ($inFunction === null) {
            return null;
        }

        foreach ($inFunction->getAttributes() as $attribute) {
            if ($attribute->getName() === Apply::class) {
                return RestrictedUsage::create(
                    errorMessage: sprintf(
                        'Method %s::recordThat() is called from %s which is an apply method.',
                        AggregateRoot::class,
                        $inFunction->getName(),
                    ),
                    identifier: 'method.noRecordThatWhenApplying',
                );
            }
        }

        return null;
    }
}
