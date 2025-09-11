<?php

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Identifier\Uuid;

class ProfileCreated
{
    public function __construct(
        public readonly Uuid $id,
        public readonly string $name
    ) {
    }
}