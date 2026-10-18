<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Identifier\RamseyUuidV7Behaviour;

final class StockId implements Identifier
{
    use RamseyUuidV7Behaviour;

    public static function create(): self
    {
        return self::fromString('9247e40b-bd9b-4592-bb93-28986df07e2b');
    }
}
