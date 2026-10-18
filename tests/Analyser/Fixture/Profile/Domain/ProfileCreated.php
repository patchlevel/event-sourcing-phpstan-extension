<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[IdNormalizer(Uuid::class)]
        public readonly Uuid $id,
        public readonly string $name,
    ) {
    }
}
