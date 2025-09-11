<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends  BasicProjection<int> */
final class NextInvoiceNumberProjection extends BasicProjection
{
    /** @return list<string> */
    public function tagFilter(): array
    {
        return [];
    }

    public function initialState(): int
    {
        return 1;
    }

    public function applyInvoiceCreated(int $state, InvoiceCreated $event): int
    {
        return $state + 1;
    }
}
