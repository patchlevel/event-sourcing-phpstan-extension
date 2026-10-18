<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension;

use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

final class AggregateRootExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isAggregate($property);
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isAggregate($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isAggregate($property);
    }

    private function isAggregate(PropertyReflection $property): bool
    {
        return EventSourcing::isAggregate($property->getDeclaringClass());
    }
}
