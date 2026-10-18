<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Infrastructure;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Application\CreateInvoice;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain\ProfileCreated;

#[Processor('first_invoice')]
final class FirstInvoiceProcessor
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event): void
    {
        $this->commandBus->dispatch(new CreateInvoice($event->id->toString()));
    }
}
