# Getting Started

This guide shows you how to enable the extension and what each of its checks does.
We use a small `Profile` aggregate as the running example throughout the documentation.

## Installation

Install the extension as a dev dependency:

```bash
composer require --dev patchlevel/event-sourcing-phpstan-extension
```
## Enable the extension

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is enabled automatically and you can skip this step. Otherwise include the shipped configuration in your `phpstan.neon`:

```neon
includes:
    - vendor/patchlevel/event-sourcing-phpstan-extension/extension.neon
```
:::note
The extension registers all of its checks at once. They are enabled by default and single rules can be turned off, see [configuration](#configuration).
:::

## The example aggregate

Here is a typical aggregate from the event-sourcing library. Its state lives in typed properties
that are assigned inside apply methods, not in a constructor.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

final class Profile extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
    private string $name;

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
    }

    public function name(): string
    {
        return $this->name;
    }
}
```
## Property initialization

PHPStan with `checkUninitializedProperties: true` reports typed properties that are never assigned
in the constructor. For an aggregate that is a false positive, because the properties are filled when
the events are applied. The extension knows that `Profile` is an aggregate and marks `$id` and `$name`
as initialized, so the analysis passes.

:::note
This works for both aggregate roots and child aggregates: any class implementing `AggregateRoot` or `ChildAggregate` has its properties treated as initialized.
:::

## Recording in apply methods

Apply methods are also called while an aggregate is rebuilt from its stored events. If you record a
new event from inside an apply method, that event is recorded again on every replay. The extension
flags this:

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

final class Profile extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
    private string $name;

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name;
        $this->recordThat(new ProfileCreated($event->id, $event->name)); // reported
    }
}
```
Running PHPStan now produces:

```
Method Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot::recordThat() records
an event and is called from apply method applyProfileCreated().
```
:::note
The check also follows calls into helper methods, so hiding `recordThat()` behind another method does not bypass the rule.
:::

## Writing state outside apply methods

The state of an aggregate must only change inside apply methods. That is what makes the state
reproducible: every change is the result of an applied event. A property that is written anywhere
else, for example directly in a command method, is not backed by an event, so the change is silently
lost the next time the aggregate is loaded from the store. The extension flags every such write:

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Aggregate\Uuid;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

final class Profile extends BasicAggregateRoot
{
    #[Id]
    private Uuid $id;
    private string $name;

    public static function create(Uuid $id, string $name): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $name));
        $self->name = $name; // reported

        return $self;
    }

    #[Apply]
    protected function applyProfileCreated(ProfileCreated $event): void
    {
        $this->id = $event->id;
        $this->name = $event->name; // allowed
    }
}
```
Running PHPStan now produces:

```
Aggregate state property "name" should only be written in an #[Apply] method,
but is written in "Profile::create()".
💡 Record an event instead and change the state in an #[Apply] method.
```
It also covers every way a property can be mutated: plain assignments, compound assignments like `.=` and `+=`,
increments and decrements, array writes like `$this->items[] = ...`, list destructuring, `unset()` and static
properties.

:::info
This also applies to helper methods inside the aggregate: a private method that assigns a property is reported at the offending line, no matter where it is called from.
:::

## Configuration

All rules are enabled by default. You can deactivate single rules in your `phpstan.neon`,
the same way PHPStan handles its own rules:

```neon
parameters:
    patchlevelEventSourcing:
        propertyInitialization: false
        noRecordThatWhenApplying: false
        noStateWriteWhenNotApplying: false
```
## Result

With the extension enabled, PHPStan understands your aggregates: it stops complaining about
properties that are initialized through events, it fails the build when an apply method records
an event, and it fails the build when aggregate state is written outside an apply method. You get
accurate static analysis without writing a single annotation.

## Learn more

* [What the extension offers](introduction.md)
* [How to build aggregates with patchlevel/event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest)
