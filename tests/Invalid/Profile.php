<?php

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Invalid;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
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
    private string $email;
    private string $lastName;
    private int $count = 0;

    /** @var array<int, string> */
    private array $items = [];

    /** @var list<self> */
    private static array $instances = [];

    #[Handle]
    public static function create(Uuid $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));
        $self->name = 'asd';
        $self->changeStateHidden();

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
        $this->lastName = $event->name;
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

    public function changeStateHidden(): void
    {
        $this->name = 'invalid';
    }

    public function otherWriteForms(): void
    {
        $this->name .= '!';
        $this->count++;
        --$this->count;
        $this->items[] = 'appended';
        $this->items[0] = 'overwritten';
        [$this->name, $this->count] = ['destructured', 1];
        unset($this->items[0]);
        self::$instances = [];
    }

    public function count(): int
    {
        return $this->count;
    }

    /** @return array<int, string> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<self> */
    public static function instances(): array
    {
        return self::$instances;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }
}