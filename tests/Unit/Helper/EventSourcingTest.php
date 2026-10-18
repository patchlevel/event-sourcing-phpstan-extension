<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcingPHPStanExtension\Helper\EventSourcing;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\CreateInvoiceHandler;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\Events\StockCreated;
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

    public function testAttributes(): void
    {
        $attributes = self::classReflection(Stock::class)->getAttributes();

        self::assertCount(1, EventSourcing::attributes($attributes, Aggregate::class));
        self::assertSame([], EventSourcing::attributes($attributes, Event::class));
    }

    private static function classReflection(string $class): ClassReflection
    {
        return self::createReflectionProvider()->getClass($class);
    }
}
