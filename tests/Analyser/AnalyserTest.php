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
use function fclose;
use function file_put_contents;
use function getenv;
use function implode;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;
use function tmpfile;

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
        [$exitCode, $output, $errorOutput] = self::analyse('eventSourcingJson', __DIR__ . '/disabled.neon');

        self::assertSame(1, $exitCode, $errorOutput);
        self::assertStringContainsString('The analyser is disabled.', $output);
    }

    private function assertSnapshot(string $errorFormat, string $snapshot): void
    {
        [$exitCode, $output, $errorOutput] = self::analyse($errorFormat, __DIR__ . '/analyser.neon');

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($snapshot, $output);
        }

        self::assertSame(0, $exitCode, $output . $errorOutput);
        self::assertStringEqualsFile($snapshot, $output, $errorOutput);
    }

    /**
     * Only the standard output is compared, the error output may contain warnings of the PHP setup.
     *
     * @return array{int, string, string}
     */
    private static function analyse(string $errorFormat, string $config): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/vendor/bin/phpstan',
            'analyse',
            '--no-progress',
            '--configuration=' . $config,
            '--error-format=' . $errorFormat,
        ];

        $errorStream = tmpfile();

        if ($errorStream === false) {
            throw new RuntimeException('Could not create a temporary file for the error output.');
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $errorStream], $pipes);

        if ($process === false) {
            throw new RuntimeException(sprintf('Could not run "%s".', implode(' ', $command)));
        }

        $output = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        $errorOutput = (string)stream_get_contents($errorStream, offset: 0);
        fclose($errorStream);

        return [$exitCode, $output, $errorOutput];
    }
}
