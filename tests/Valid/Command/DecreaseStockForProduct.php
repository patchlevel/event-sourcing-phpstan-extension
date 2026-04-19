<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Command;

use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\ProductId;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\StockId;

final class DecreaseStockForProduct
{
    public function __construct(
        #[Id]
        public StockId $stockId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
