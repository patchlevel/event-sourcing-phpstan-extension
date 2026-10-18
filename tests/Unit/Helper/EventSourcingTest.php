<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper\Fixture\MultiCommandHandler;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper\Fixture\StockCountProjection;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Command\AdjustStockForProduct;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Command\DecreaseStockForProduct;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\CreateInvoice;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\CreateInvoiceHandler;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockAdjusted;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockCreated;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockDecreased;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Stock;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Testing\PHPStanTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventSourcing::class)]
final class EventSourcingTest extends PHPStanTestCase
{
    public function testIsAggregate(): void
    {
        self::assertTrue(EventSourcing::isAggregate(self::classReflection(Stock::class)));
        self::assertFalse(EventSourcing::isAggregate(self::classReflection(StockCreated::class)));
        self::assertFalse(EventSourcing::isAggregate(self::classReflection(CreateInvoiceHandler::class)));
    }

    public function testIsApplyMethod(): void
    {
        $stock = self::classReflection(Stock::class);

        self::assertTrue(EventSourcing::isApplyMethod($stock->getNativeMethod('applyStockCreated')));
        self::assertFalse(EventSourcing::isApplyMethod($stock->getNativeMethod('decreaseStock')));
    }

    public function testHasAttribute(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App;

            use Patchlevel\EventSourcing\Attribute\Apply;
            use Patchlevel\EventSourcing\Attribute\Id;

            final class Profile
            {
                #[Id]
                private string $id;

                private string $name;

                #[Apply]
                public function applyCreated(): void {}

                #[\Patchlevel\EventSourcing\Attribute\Apply]
                public function applyFullyQualified(): void {}

                #[Apply2]
                public function applySimilar(): void {}

                public function other(): void {}
            }
            PHP;

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser(new NameResolver());
        $class = (new NodeFinder())->findFirstInstanceOf($traverser->traverse($parser->parse($code) ?? []), Class_::class);

        self::assertInstanceOf(Class_::class, $class);

        [$id, $name] = $class->getProperties();

        self::assertTrue(EventSourcing::hasAttribute($id, Id::class));
        self::assertFalse(EventSourcing::hasAttribute($name, Id::class));

        $methods = $class->getMethods();

        self::assertTrue(EventSourcing::hasAttribute($methods[0], Apply::class));
        self::assertTrue(EventSourcing::hasAttribute($methods[1], Apply::class));
        self::assertFalse(EventSourcing::hasAttribute($methods[2], Apply::class));
        self::assertFalse(EventSourcing::hasAttribute($methods[3], Apply::class));
    }

    public function testHandledCommands(): void
    {
        $stock = self::classReflection(Stock::class);

        self::assertSame([DecreaseStockForProduct::class], EventSourcing::handledCommands($stock->getNativeMethod('decreaseStock')));
        self::assertSame([AdjustStockForProduct::class], EventSourcing::handledCommands($stock->getNativeMethod('adjustStock')));
        self::assertSame([], EventSourcing::handledCommands($stock->getNativeMethod('initialize')));

        $handler = self::classReflection(CreateInvoiceHandler::class);

        self::assertSame([CreateInvoice::class], EventSourcing::handledCommands($handler->getNativeMethod('__invoke')));
    }

    public function testHandledCommandsFromAttributeArguments(): void
    {
        $handler = self::classReflection(MultiCommandHandler::class);

        self::assertSame(
            [AdjustStockForProduct::class],
            EventSourcing::handledCommands($handler->getNativeMethod('handleExplicit')),
        );
        self::assertSame(
            [AdjustStockForProduct::class, DecreaseStockForProduct::class],
            EventSourcing::handledCommands($handler->getNativeMethod('handleRepeated')),
        );
    }

    public function testHandledCommandsFromParameterTypes(): void
    {
        $handler = self::classReflection(MultiCommandHandler::class);

        self::assertSame(
            [AdjustStockForProduct::class, DecreaseStockForProduct::class],
            EventSourcing::handledCommands($handler->getNativeMethod('handleUnion')),
        );
        self::assertSame([], EventSourcing::handledCommands($handler->getNativeMethod('handleWithoutParameter')));
        self::assertSame([], EventSourcing::handledCommands($handler->getNativeMethod('noHandler')));
    }

    public function testAppliedEventsOfAggregate(): void
    {
        $stock = self::classReflection(Stock::class);

        self::assertSame([StockCreated::class], EventSourcing::appliedEvents($stock->getNativeMethod('applyStockCreated'), 0));
        self::assertSame([StockDecreased::class], EventSourcing::appliedEvents($stock->getNativeMethod('applyStockDecreased'), 0));
        self::assertSame([], EventSourcing::appliedEvents($stock->getNativeMethod('decreaseStock'), 0));
    }

    public function testAppliedEventsOfProjection(): void
    {
        $projection = self::classReflection(StockCountProjection::class);

        // the first parameter of a projection apply method is the state
        self::assertSame([StockDecreased::class], EventSourcing::appliedEvents($projection->getNativeMethod('applyStockDecreased'), 1));
        self::assertSame([], EventSourcing::appliedEvents($projection->getNativeMethod('applyStockDecreased'), 2));
        self::assertSame([StockAdjusted::class], EventSourcing::appliedEvents($projection->getNativeMethod('reset'), 1));
        self::assertSame([], EventSourcing::appliedEvents($projection->getNativeMethod('tagFilter'), 1));
    }

    public function testAttributes(): void
    {
        $attributes = self::classReflection(Stock::class)->getAttributes();

        self::assertCount(1, EventSourcing::attributes($attributes, Aggregate::class));
        self::assertSame([], EventSourcing::attributes($attributes, Event::class));
    }

    public function testStringArgument(): void
    {
        $stock = self::classReflection(Stock::class);

        [$aggregate] = EventSourcing::attributes($stock->getAttributes(), Aggregate::class);

        self::assertSame('stock', EventSourcing::stringArgument($aggregate, 'name'));
        self::assertNull(EventSourcing::stringArgument($aggregate, 'missing'));

        $handle = self::classReflection(MultiCommandHandler::class)->getNativeMethod('handleExplicit')->getAttributes()[0];

        self::assertSame(AdjustStockForProduct::class, EventSourcing::stringArgument($handle, 'commandClass'));

        // #[Handle] without an argument has the default null, which is not a string
        $handle = $stock->getNativeMethod('decreaseStock')->getAttributes()[0];

        self::assertNull(EventSourcing::stringArgument($handle, 'commandClass'));
    }

    private static function classReflection(string $class): ClassReflection
    {
        return self::createReflectionProvider()->getClass($class);
    }
}
