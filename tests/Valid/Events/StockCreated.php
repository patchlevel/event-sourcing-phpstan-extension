<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\StockId;

#[Event('stock.created')]
final class StockCreated
{
    public function __construct(
        public StockId $stockId,
    ) {
    }
}
