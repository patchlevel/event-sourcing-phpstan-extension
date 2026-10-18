<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Projection\ProjectionBuilder;

final class InvoiceStatistics
{
    public function __construct(
        private readonly ProjectionBuilder $projectionBuilder,
    ) {
    }

    public function invoices(): int
    {
        $state = $this->projectionBuilder->build([
            'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
            'invoiceCount' => new InvoiceCountProjection(),
        ]);

        return $state['nextInvoiceNumber'] - 1 + $state['invoiceCount'];
    }
}
