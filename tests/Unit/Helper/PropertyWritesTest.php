<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Helper;

use Patchlevel\EventSourcingPHPStanExtension\Helper\PropertyWrites;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(PropertyWrites::class)]
final class PropertyWritesTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function provideTargets(): iterable
    {
        yield 'assign' => ['$this->a = 1;', ['$this->a']];
        yield 'assign op' => ['$this->a += 1;', ['$this->a']];
        yield 'assign coalesce' => ['$this->a ??= 1;', ['$this->a']];
        yield 'assign ref' => ['$this->a = &$b;', ['$this->a']];
        yield 'pre increment' => ['++$this->a;', ['$this->a']];
        yield 'pre decrement' => ['--$this->a;', ['$this->a']];
        yield 'post increment' => ['$this->a++;', ['$this->a']];
        yield 'post decrement' => ['$this->a--;', ['$this->a']];
        yield 'variable' => ['$a = 1;', ['$a']];
        yield 'array dimension' => ['$this->a["x"] = 1;', ['$this->a["x"]']];
        yield 'list' => ['[$this->a, $this->b] = $c;', ['[$this->a, $this->b]']];
        yield 'no write' => ['$this->a;', []];
        yield 'call' => ['$this->a();', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('provideTargets')]
    public function testTargets(string $code, array $expected): void
    {
        self::assertSame($expected, self::print(PropertyWrites::targets(self::expression($code))));
    }

    public function testTargetsOfUnset(): void
    {
        $unset = self::statements('unset($this->a, $this->b["x"]);')[0];

        self::assertInstanceOf(Unset_::class, $unset);
        self::assertSame(['$this->a', '$this->b["x"]'], self::print(PropertyWrites::targets($unset)));
        self::assertSame([], PropertyWrites::targets($unset, withUnset: false));
    }

    public function testFindTargets(): void
    {
        $statements = self::statements(<<<'PHP'
            $this->a = 1;
            if ($condition) {
                $this->b++;
            }
            $closure = function () {
                $this->c = 2;
            };
            unset($this->d);
            $this->e();
            PHP);

        self::assertSame(
            ['$this->a', '$this->b', '$closure', '$this->c', '$this->d'],
            self::print(PropertyWrites::findTargets($statements)),
        );
        self::assertSame(
            ['$this->a', '$this->b', '$closure', '$this->c'],
            self::print(PropertyWrites::findTargets($statements, withUnset: false)),
        );
    }

    public function testFindTargetsWithNestedAssignments(): void
    {
        $statements = self::statements('$this->a = $this->b = 1;');

        self::assertSame(['$this->a', '$this->b'], self::print(PropertyWrites::findTargets($statements)));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function provideProperties(): iterable
    {
        yield 'property' => ['$this->a', ['$this->a']];
        yield 'property of another object' => ['$other->a', ['$other->a']];
        yield 'static property' => ['self::$a', ['self::$a']];
        yield 'array dimension' => ['$this->a["x"]["y"]', ['$this->a']];
        yield 'array append' => ['$this->a[]', ['$this->a']];
        yield 'list' => ['[$this->a, , [$this->b], "key" => $this->c]', ['$this->a', '$this->b', '$this->c']];
        yield 'list with variable' => ['[$a, $this->b]', ['$this->b']];
        yield 'variable' => ['$a', []];
        yield 'array dimension of variable' => ['$a["x"]', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('provideProperties')]
    public function testProperties(string $target, array $expected): void
    {
        $assign = self::expression($target . ' = $value;');

        self::assertInstanceOf(Expr\Assign::class, $assign);
        self::assertSame($expected, self::print(PropertyWrites::properties($assign->var)));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function provideOwnPropertyName(): iterable
    {
        yield 'own property' => ['$this->a', 'a'];
        yield 'property of another object' => ['$other->a', null];
        yield 'dynamic property name' => ['$this->{$name}', null];
        yield 'static property' => ['self::$a', null];
    }

    #[DataProvider('provideOwnPropertyName')]
    public function testOwnPropertyName(string $code, string|null $expected): void
    {
        $propertyFetch = self::expression($code . ';');

        self::assertTrue($propertyFetch instanceof PropertyFetch || $propertyFetch instanceof StaticPropertyFetch);
        self::assertSame($expected, PropertyWrites::ownPropertyName($propertyFetch));
    }

    /** @return array<Node\Stmt> */
    private static function statements(string $code): array
    {
        return (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php ' . $code) ?? [];
    }

    private static function expression(string $code): Expr
    {
        $statement = self::statements($code)[0] ?? null;

        self::assertInstanceOf(Expression::class, $statement);

        return $statement->expr;
    }

    /**
     * @param list<Expr> $expressions
     *
     * @return list<string>
     */
    private static function print(array $expressions): array
    {
        $printer = new Standard();

        return array_map(static fn (Expr $expression): string => $printer->prettyPrintExpr($expression), $expressions);
    }
}
