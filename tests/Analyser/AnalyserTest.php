<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser;

use Patchlevel\EventSourcingPHPStanExtension\Analyser\GraphvizFormatter;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\JsonFormatter;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\ProjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function getenv;
use function implode;
use function sprintf;

use const PHP_BINARY;

/**
 * Runs PHPStan with the analyser on the fixture domain and compares the output with the snapshots.
 * Run the tests with UPDATE_SNAPSHOTS=1 to write the current output as new snapshots.
 */
#[CoversClass(ProjectFactory::class)]
#[CoversClass(JsonFormatter::class)]
#[CoversClass(GraphvizFormatter::class)]
final class AnalyserTest extends TestCase
{
    public function testJson(): void
    {
        $this->assertSnapshot('eventSourcingJson', __DIR__ . '/snapshot.json');
    }

    public function testGraphviz(): void
    {
        $this->assertSnapshot('eventSourcingGraphviz', __DIR__ . '/snapshot.dot');
    }

    public function testDisabledAnalyser(): void
    {
        [$exitCode, $output] = self::analyse('eventSourcingJson', __DIR__ . '/disabled.neon');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('The analyser is disabled.', $output);
    }

    private function assertSnapshot(string $errorFormat, string $snapshot): void
    {
        [$exitCode, $output] = self::analyse($errorFormat, __DIR__ . '/analyser.neon');

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($snapshot, $output);
        }

        self::assertSame(0, $exitCode, $output);
        self::assertStringEqualsFile($snapshot, $output);
    }

    /** @return array{int, string} */
    private static function analyse(string $errorFormat, string $config): array
    {
        $command = sprintf(
            '%s %s analyse --no-progress --configuration=%s --error-format=%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__, 2) . '/vendor/bin/phpstan'),
            escapeshellarg($config),
            escapeshellarg($errorFormat),
        );

        $output = [];
        $exitCode = 0;

        if (exec($command . ' 2>&1', $output, $exitCode) === false) {
            throw new RuntimeException(sprintf('Could not run "%s".', $command));
        }

        return [$exitCode, implode("\n", $output) . "\n"];
    }
}
