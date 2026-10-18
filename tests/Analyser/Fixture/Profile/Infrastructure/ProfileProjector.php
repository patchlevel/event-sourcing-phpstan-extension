<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Infrastructure;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain\ProfileCreated;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain\ProfileRenamed;

#[Projector('profile')]
final class ProfileProjector
{
    /** @var list<string> */
    private array $names = [];

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event): void
    {
        $this->names[] = $event->name;
    }

    #[Subscribe(ProfileRenamed::class)]
    public function onProfileRenamed(ProfileRenamed $event): void
    {
        $this->names[] = $event->name;
    }
}
