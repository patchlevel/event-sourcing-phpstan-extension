<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\ProductId;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\StockId;

#[Event('stock.adjusted')]
final class StockAdjusted
{
    public function __construct(
        public StockId $stockId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
