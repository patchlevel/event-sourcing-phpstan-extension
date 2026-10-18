<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.renamed')]
final class ProfileRenamed
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
