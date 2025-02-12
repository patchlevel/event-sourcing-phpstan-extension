<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Aggregate\Uuid;

final class Usage
{
    public function do(): bool
    {
        $profile = Profile::create(Uuid::generate(), 'peter');
        $name = $profile->name();

        if ($name === 'peter') {
            return true;
        }

        return false;
    }
}