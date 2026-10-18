<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper\Fixture;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockAdjusted;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockDecreased;

/** @extends BasicProjection<int> */
final class StockCountProjection extends BasicProjection
{
    /** @return list<string> */
    public function tagFilter(): array
    {
        return [];
    }

    public function initialState(): int
    {
        return 0;
    }

    #[Apply]
    public function applyStockDecreased(int $state, StockDecreased $event): int
    {
        return $state - $event->quantity;
    }

    #[Apply(StockAdjusted::class)]
    public function reset(int $state): int
    {
        return 0;
    }
}
