# Getting Started

This guide shows you how to enable the extension and what each of its two rules does.
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
The extension registers two rules at once. There is nothing else to configure.
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
Method Patchlevel\EventSourcing\Aggregate\AggregateRoot::recordThat() is called
from applyProfileCreated which is an apply method.
```
:::info
The check also follows calls into helper methods, so hiding `recordThat()` behind another method does not bypass the rule.
:::

## Result

With the extension enabled, PHPStan understands your aggregates: it stops complaining about
properties that are initialized through events, and it fails the build when an apply method records
an event. You get accurate static analysis without writing a single annotation.

## Learn more

* [What the extension offers](introduction.md)
* [How to build aggregates with patchlevel/event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest)
