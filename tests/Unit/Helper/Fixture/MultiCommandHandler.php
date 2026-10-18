<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper\Fixture;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Command\AdjustStockForProduct;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Command\DecreaseStockForProduct;

final class MultiCommandHandler
{
    #[Handle(AdjustStockForProduct::class)]
    public function handleExplicit(object $command): void
    {
    }

    #[Handle]
    public function handleUnion(AdjustStockForProduct|DecreaseStockForProduct $command): void
    {
    }

    #[Handle(AdjustStockForProduct::class)]
    #[Handle(DecreaseStockForProduct::class)]
    public function handleRepeated(object $command): void
    {
    }

    #[Handle]
    public function handleWithoutParameter(): void
    {
    }

    public function noHandler(DecreaseStockForProduct $command): void
    {
    }
}
