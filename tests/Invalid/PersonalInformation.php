<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Invalid;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\NameChanged;

class PersonalInformation extends BasicChildAggregate
{
    private string $name;

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
        $this->recordThat(new NameChanged($event->name));
    }

    public function name(): string
    {
        return $this->name;
    }
}
