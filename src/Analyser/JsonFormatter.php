<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class JsonFormatter implements ErrorFormatter
{
    public function __construct(
        private readonly bool $enabled,
        private readonly ErrorFormatter $fallbackFormatter,
    ) {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if (!$this->enabled) {
            $output->getStyle()->error('The analyser is disabled. Enable it with the "patchlevelEventSourcing.analyser" parameter.');

            return 1;
        }

        // without a complete analysis the model would be incomplete, so the internal errors are shown instead
        if ($analysisResult->hasInternalErrors()) {
            return $this->fallbackFormatter->formatErrors($analysisResult, $output);
        }

        $project = (new ProjectFactory())->create($analysisResult->getCollectedData());

        $output->writeRaw(json_encode($project, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        return 0;
    }
}
