<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

final class CreateInvoice
{
    public function __construct(
        public readonly int $money,
    ) {
    }
}
