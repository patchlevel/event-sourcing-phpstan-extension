[![License](https://poser.pugx.org/patchlevel/event-sourcing-psalm-plugin/license)](//packagist.org/packages/patchlevel/event-sourcing-psalm-plugin)

# event-sourcing-phpstan-extension

phpstan extension for [event-sourcing](https://github.com/patchlevel/event-sourcing) library.

## installation

```
composer require --dev patchlevel/event-sourcing-phpstan-extension
```

add the extension to your `phpstan.neon`:

```neon
includes:
	- vendor/patchlevel/event-sourcing-phpstan-extension/extension.neon
```