<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Domain;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('invoice.created')]
final class InvoiceCreated
{
    public function __construct(
        #[EventTag(prefix: 'invoice')]
        public readonly int $invoiceNumber,
        public readonly string $customer,
    ) {
    }
}
