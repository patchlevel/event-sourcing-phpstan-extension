# Analyser

The analyser reads your event sourced code during a PHPStan run and turns it into a model of your domain: which
commands are handled, which events they record, which projections a decision reads and which subscribers react to the
events. You render that model as a diagram inspired by [Event Storming](https://www.eventstorming.com/) or export it as
JSON, see [analyser output](analyser-output.md).

:::note
The analyser does not check anything. It only collects data, so it never adds errors to your normal PHPStan run.
:::

## Enable the analyser

The analyser is disabled by default, because collecting the data costs time and space in the result cache on every
run. Enable it in your `phpstan.neon`:

```neon
parameters:
    patchlevelEventSourcing:
        analyser: true
```
If you do not want to pay for the analyser in your regular runs, enable it in a separate configuration file and use
that one only to render the model:

```neon
includes:
    - phpstan.neon

parameters:
    patchlevelEventSourcing:
        analyser: true
```
Save it as `phpstan-analyser.neon` and pass it to PHPStan whenever you want to render the model:

```bash
vendor/bin/phpstan analyse --configuration=phpstan-analyser.neon --error-format=eventSourcingGraphviz | dot -Tsvg > domain.svg
```
:::warning
The model only contains the code PHPStan analyses. If you pass single paths to `phpstan analyse`, everything outside
of them is missing from the model.
:::

## Aggregates

An aggregate is detected by the `#[Aggregate]` attribute, and its name becomes the label. Every event it records
through `recordThat()` belongs to the aggregate, and every command of a `#[Handle]` method is drawn inside of it.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
    private string $name;

    #[Handle]
    public static function create(CreateProfile $command): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($command->id, $command->name));

        return $self;
    }

    #[Handle(ChangeName::class)]
    public function changeName(string $name): void
    {
        if ($this->name === $name) {
            return;
        }

        $this->recordNameChanged($name);
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
        $this->name = $event->name;
    }

    private function recordNameChanged(string $name): void
    {
        $this->recordThat(new NameChanged($this->id, $name));
    }
}
```
The command of a `#[Handle]` method comes from the type of its first parameter, or from the explicit command class of
the attribute, like the command bus of the library resolves it.

:::note
The analyser follows the methods of the own class, also the ones of traits and parent classes. The `changeName()`
handler above is connected to `NameChanged`, although the event is recorded in a helper method.
:::

## Events

An event is detected by the `#[Event]` attribute, and its name becomes the label:

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Identifier\Uuid;

#[Event('profile.name_changed')]
final class NameChanged
{
    public function __construct(
        public readonly Uuid $profileId,
        public readonly string $name,
    ) {
    }
}
```
An event that is recorded or subscribed to but not analysed, for example because it lives in another package, is
still part of the model and labelled with its short class name.

## Decisions

With the [dynamic consistency boundary](https://patchlevel.dev/docs/event-sourcing/latest/dynamic-consistency-boundary),
a command is handled by a service instead of an aggregate. The analyser connects the command to the events the handler
appends to the decision, and to the projections it reads to decide:

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;

final class ChangeNameHandler
{
    #[Handle]
    public function __invoke(ChangeName $command, Decision $decision): void
    {
        $state = $decision->read([
            'exists' => new ProfileExists($command->profileId),
            'name' => new ProfileName($command->profileId),
        ]);

        if (!$state['exists'] || $state['name'] === $command->name) {
            return;
        }

        $decision->append([new NameChanged($command->profileId, $command->name)]);
    }
}
```
Every class implementing the `Projection` interface is part of the model, together with the events of its `#[Apply]`
methods. In the diagram, the events point to the projections, and the projections point to the commands that read
them.

## Subscribers

A subscriber reacts to events. The analyser detects the three kinds of subscribers by their attribute and draws each
kind in its own color:

* `#[Projector]` builds a read model
* `#[Processor]` runs side effects and may dispatch follow up commands
* `#[Subscriber]` is the generic form

The events come from the `#[Subscribe]` methods, and every command the subscriber dispatches through the command bus
adds an edge to that command:

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\CommandBus\CommandBus;

#[Processor('welcome_mail')]
final class WelcomeMailProcessor
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event): void
    {
        $this->commandBus->dispatch(new SendWelcomeMail($event->id));
    }
}
```
:::note
Commands dispatched through the `CommandBus` of the library and through the `MessageBusInterface` of Symfony Messenger
are detected.
:::

:::tip
A subscriber with `#[Subscribe(Subscribe::ALL)]` listens to every event. It is labelled with "all events" instead of
an edge from every single event.
:::

## Symfony controllers

Controllers close the loop from the request that dispatches a command to the read model a page shows. A class with
the `#[AsController]` attribute or extending the `AbstractController` of Symfony is a controller. It is part of the
model as soon as it dispatches a command or calls a method of a subscriber:

```php
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\Identifier\Uuid;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class CreateProfileController
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(string $name): void
    {
        $this->commandBus->dispatch(new CreateProfile(Uuid::generate(), $name));
    }
}
```
## Bounded contexts

The analyser groups everything into bounded contexts, so the diagram stays readable for large applications. It reads
the namespace of each class and looks for a `Domain`, `Application` or `Infrastructure` segment. The segment right
before it is the name of the context, so `App\Profile\Domain\Profile` belongs to the `Profile` context and
`App\Billing\Application\CreateInvoice` to the `Billing` context.

:::tip
A class without one of these segments stays in the model, but outside of any context. Use a consistent
`App\<Context>\<Layer>` namespace layout and every class lands in the right context.
:::

## Learn more

* [How to render the model as a diagram or JSON](analyser-output.md)
* [How to configure the extension](getting-started.md#configuration)
* [How to make decisions with the dynamic consistency boundary](https://patchlevel.dev/docs/event-sourcing/latest/dynamic-consistency-boundary)
