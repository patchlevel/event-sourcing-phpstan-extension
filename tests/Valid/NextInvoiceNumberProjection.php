<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid;

use Patchlevel\EventSourcing\DCB\EventRouter;
use Patchlevel\EventSourcing\DCB\Projection;

/**
 * @implements Projection<int>
 */
final class NextInvoiceNumberProjection implements Projection
{
    use EventRouter;

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
