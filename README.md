[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-phpstan-extension/v)](//packagist.org/packages/patchlevel/event-sourcing-phpstan-extension)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-phpstan-extension/license)](//packagist.org/packages/patchlevel/event-sourcing-phpstan-extension)

# event-sourcing-phpstan-extension

"PHPStan that understands your aggregates and catches event sourcing mistakes before runtime."

## Features

* [Property initialization](https://patchlevel.dev/docs/event-sourcing-phpstan-extension/latest/getting-started#property-initialization) for aggregate roots and child aggregates, so PHPStan does not report false uninitialized property errors.
* [Recording in apply methods](https://patchlevel.dev/docs/event-sourcing-phpstan-extension/latest/getting-started#recording-in-apply-methods) is reported as an error, because recording events while replaying them leads to duplicated events.
* [Writing state outside apply methods](https://patchlevel.dev/docs/event-sourcing-phpstan-extension/latest/getting-started#writing-state-outside-apply-methods) is reported as an error, because state that is not derived from an event is lost when the aggregate is reloaded.

## Installation

```bash
composer require --dev patchlevel/event-sourcing-phpstan-extension
```

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is registered automatically. Otherwise register it in your `phpstan.neon`:

```neon
includes:
    - vendor/patchlevel/event-sourcing-phpstan-extension/extension.neon
```

## Documentation

* Latest [Docs](https://patchlevel.dev/docs/event-sourcing-phpstan-extension/latest)
* Related [Blog](https://patchlevel.dev/blog)

## Integration

* [patchlevel/event-sourcing](https://github.com/patchlevel/event-sourcing)

## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://patchlevel.dev/our-backward-compatibility-promise).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.
