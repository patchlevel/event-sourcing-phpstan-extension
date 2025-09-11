<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\DecisionModelBuilder;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;

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
