<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;

final class CreateInvoiceHandler
{
    #[Handle]
    public function __invoke(CreateInvoice $command, Decision $decision): void
    {
        $state = $decision->read([
            'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
            'invoiceCount' => new InvoiceCountProjection(),
        ]);

        [$nextInvoiceNumber, $invoiceCount] = $decision->read([
            new NextInvoiceNumberProjection(),
            new InvoiceCountProjection(),
        ]);

        $decision->append([
            new InvoiceCreated(
                $state['nextInvoiceNumber'] + $state['invoiceCount'] + $nextInvoiceNumber + $invoiceCount,
                $decision->read(new NextInvoiceNumberProjection()) + $decision->read(new InvoiceCountProjection()),
            ),
        ]);
    }
}
