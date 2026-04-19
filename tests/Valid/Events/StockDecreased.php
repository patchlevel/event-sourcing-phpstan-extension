<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\ProductId;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\StockId;

#[Event('stock.decreased')]
final class StockDecreased
{
    public function __construct(
        public StockId $stockId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
