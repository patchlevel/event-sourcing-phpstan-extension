<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Domain;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Application\ArchiveProfile;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Application\CreateProfile;
use Patchlevel\EventSourcingPHPStanExtension\Tests\Analyser\Fixture\Profile\Application\RenameProfile;
use RuntimeException;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    use ArchivingBehaviour;

    #[Id]
    private Uuid $id;
    private string $name;
    private bool $archived;

    /** the command is resolved from the type of the first parameter */
    #[Handle]
    public static function create(CreateProfile $command): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    /** the command is given explicitly, the event is recorded in a helper method */
    #[Handle(RenameProfile::class)]
    public function rename(string $name): void
    {
        $this->guardNotArchived();

        if ($this->name === $name) {
            return;
        }

        $this->recordRenamed($name);
    }

    /** the event is recorded in a method of a trait */
    #[Handle]
    public function archive(ArchiveProfile $command): void
    {
        $this->guardNotArchived();
        $this->recordArchived();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
        $this->archived = false;
    }

    #[Apply]
    protected function applyProfileRenamed(ProfileRenamed $event): void
    {
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyProfileArchived(ProfileArchived $event): void
    {
        $this->archived = true;
    }

    private function guardNotArchived(): void
    {
        if ($this->archived) {
            throw new RuntimeException('profile is archived');
        }
    }

    private function recordRenamed(string $name): void
    {
        $this->recordThat(new ProfileRenamed($name));
    }
}
