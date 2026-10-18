<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain;

trait ArchivingBehaviour
{
    private function recordArchived(): void
    {
        $this->recordThat(new ProfileArchived());
    }
}
