<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Infrastructure;

use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Application\CreateProfile;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class CreateProfileController
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(string $name): void
    {
        $this->commandBus->dispatch(new CreateProfile(Uuid::generate(), $name));
    }
}
