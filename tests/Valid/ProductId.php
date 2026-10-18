<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Identifier\RamseyUuidV7Behaviour;

final class ProductId implements Identifier
{
    use RamseyUuidV7Behaviour;
}
