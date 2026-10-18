<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Application;

use Patchlevel\EventSourcing\Identifier\Uuid;

final class RenameProfile
{
    public function __construct(
        public readonly Uuid $id,
        public readonly string $name,
    ) {
    }
}
