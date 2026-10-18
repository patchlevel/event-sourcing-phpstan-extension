<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Application;

final class CreateInvoice
{
    public function __construct(
        public readonly string $customer,
    ) {
    }
}
