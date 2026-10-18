<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Billing\Domain;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends BasicProjection<int> */
final class CustomerInvoicesProjection extends BasicProjection
{
    public function __construct(
        private readonly string $customer,
    ) {
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return ['customer:' . $this->customer];
    }

    public function initialState(): int
    {
        return 0;
    }

    /** the event is given explicitly */
    #[Apply(InvoiceCreated::class)]
    public function countInvoice(int $state): int
    {
        return $state + 1;
    }
}
