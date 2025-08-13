<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DCB\DecisionModelBuilder;
use Patchlevel\EventSourcing\DCB\EventAppender;

final class CreateInvoiceHandler
{
    public function __construct(
        private readonly DecisionModelBuilder $decisionModelBuilder,
        private readonly EventAppender $eventAppender,
    ) {
    }

    #[Handle]
    public function __invoke(CreateInvoice $command): void
    {
        $state = $this->decisionModelBuilder->build(
            [
                'nextInvoiceNumber' => new NextInvoiceNumberProjection(),
            ],
        );

        $this->eventAppender->append([
            new InvoiceCreated(
                $state['nextInvoiceNumber'],
                $command->money,
            ),
        ], $state->appendCondition);
    }
}
