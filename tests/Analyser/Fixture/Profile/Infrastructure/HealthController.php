<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Infrastructure;

use Symfony\Component\HttpKernel\Attribute\AsController;

/** neither dispatches a command nor reads from a subscriber, so it is not part of the model */
#[AsController]
final class HealthController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
