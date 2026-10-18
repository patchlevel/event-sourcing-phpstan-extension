<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Application;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Domain\CustomerInvoicesProjection;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Domain\InvoiceCreated;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Domain\NextInvoiceNumberProjection;
use RuntimeException;

/** a handler of the dynamic consistency boundary, the event is appended in a helper method */
final class CreateInvoiceHandler
{
    #[Handle]
    public function __invoke(CreateInvoice $command, Decision $decision): void
    {
        $state = $decision->read([
            'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
            'customerInvoices' => new CustomerInvoicesProjection($command->customer),
        ]);

        if ($state['customerInvoices'] >= 10) {
            throw new RuntimeException('too many invoices');
        }

        $this->appendInvoice($decision, $state['nextInvoiceNumber'], $command->customer);
    }

    private function appendInvoice(Decision $decision, int $invoiceNumber, string $customer): void
    {
        $decision->append([new InvoiceCreated($invoiceNumber, $customer)]);
    }
}
