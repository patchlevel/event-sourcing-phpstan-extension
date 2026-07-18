<?php

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Invalid;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\EventCollector;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\NameChanged;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Valid\ProfileCreated;

class Profile extends BasicAggregateRoot
{
    use RecordingHelper;

    #[Id]
    private Uuid $id;
    private string $name;
    private EventCollector $collector;

    public static function create(Uuid $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));

        return $self;
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
        $this->collector = new EventCollector();
        $this->recordThat(new ProfileCreated($event->id, $event->name));
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
        $this->hiddenRecordThat($event);
        $this->deeplyHiddenRecordThat($event);
        $this->traitHiddenRecordThat($event);
        $this->collectEvent($event);
    }

    public function hiddenRecordThat(NameChanged $event): void
    {
        $this->recordThat(new NameChanged($event->name));
    }

    public function deeplyHiddenRecordThat(NameChanged $event): void
    {
        $this->hiddenRecordThat($event);
    }

    public function collectEvent(NameChanged $event): void
    {
        $this->collector->recordThat($event);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}