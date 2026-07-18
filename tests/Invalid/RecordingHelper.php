<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Invalid;

use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\NameChanged;

trait RecordingHelper
{
    public function traitHiddenRecordThat(NameChanged $event): void
    {
        $this->recordThat(new NameChanged($event->name));
    }
}
