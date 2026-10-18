<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Infrastructure;

use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class ProfileListController
{
    public function __construct(
        private readonly ProfileProjector $profileProjector,
    ) {
    }

    /** @return list<string> */
    public function __invoke(): array
    {
        return $this->profileProjector->names();
    }
}
