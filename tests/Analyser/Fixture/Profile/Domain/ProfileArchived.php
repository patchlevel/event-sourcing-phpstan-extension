<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.archived')]
final class ProfileArchived
{
}
