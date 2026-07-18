# event-sourcing-phpstan-extension

A [PHPStan](https://phpstan.org/) extension for the [patchlevel/event-sourcing](https://github.com/patchlevel/event-sourcing) library.
It teaches PHPStan how aggregates work so that static analysis stays accurate on event sourced code,
and it catches a common mistake before it ever reaches runtime.

## Features

* [Property initialization](getting-started.md#property-initialization) for aggregate roots and child aggregates, so PHPStan does not report false uninitialized property errors.
* [Recording in apply methods](getting-started.md#recording-in-apply-methods) is reported as an error, because recording events while replaying them leads to duplicated events.

## Installation

```bash
composer require --dev patchlevel/event-sourcing-phpstan-extension
```
If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is registered automatically and you are done. Otherwise register it in your `phpstan.neon`:

```neon
includes:
    - vendor/patchlevel/event-sourcing-phpstan-extension/extension.neon
```
That is all the configuration the extension needs. Both rules are active as soon as the extension is registered.

## Integration

* [patchlevel/event-sourcing](https://github.com/patchlevel/event-sourcing) is the library this extension analyses.

:::tip
New to the extension? The [getting started](getting-started.md) guide walks you through enabling it and seeing both rules in action.
:::
