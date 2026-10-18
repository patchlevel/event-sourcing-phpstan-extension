# Analyser Output

The [analyser](analyser.md) renders the model of your domain through a PHPStan error formatter. Two formatters ship
with the extension: `eventSourcingGraphviz` draws a diagram inspired by Event Storming, and `eventSourcingJson`
exports the same model as data for your own tooling. You pick one with the `--error-format` option of
`phpstan analyse`.

:::warning
Both formatters only print the model. Errors PHPStan finds in your code are not shown and the exit code stays `0`,
so run your regular PHPStan check separately. Only if the analysis itself fails, the formatters print those errors
instead, because the model would be incomplete.
:::

## Graphviz

The Graphviz formatter prints the model in the [DOT](https://graphviz.org/doc/info/lang.html) language, which you turn
into an image with the `dot` binary:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingGraphviz | dot -Tsvg > domain.svg
```
`dot` is part of [Graphviz](https://graphviz.org/). Install it with your package manager, for example
`brew install graphviz` on macOS or `apt-get install graphviz` on Debian based systems. Use `-Tpng` instead of `-Tsvg`
to get a PNG.

Every [bounded context](analyser.md#bounded-contexts) becomes a dotted cluster. Inside of it, each aggregate is a
yellow cluster around its commands and events, while subscribers, projections and controllers sit next to the
aggregates. The edges follow the flow of your domain:

* a command points to the events its handlers record or append
* an event points to the subscribers and projections that use it
* a projection points to the commands whose handlers read it
* a processor or a controller points to the commands it dispatches
* a subscriber points to the controllers that call it

The colors follow the usual Event Storming palette:

| Element | Color |
| --- | --- |
| Aggregate | yellow |
| Event | orange |
| Command | blue |
| Subscriber | red |
| Processor | purple |
| Projector | green |
| Projection | light green |
| Controller | gray |

## JSON

The JSON formatter exposes the model as data, so you can build your own renderer, a documentation page or a check in
your pipeline on top of it:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingJson > domain.json
```
The top level object holds one collection per element, keyed by the class name, and the bounded contexts keyed by
their name. The bounded contexts only list class names that reference the elements in the other collections:

```json
{
    "boundedContexts": {
        "Profile": {
            "name": "Profile",
            "aggregates": ["App\\Profile\\Domain\\Profile"],
            "events": ["App\\Profile\\Domain\\NameChanged"],
            "commands": ["App\\Profile\\Application\\ChangeName"],
            "subscribers": [],
            "projections": ["App\\Profile\\Domain\\ProfileName"],
            "userInterfaces": []
        }
    },
    "aggregates": {
        "App\\Profile\\Domain\\Profile": {
            "name": "profile",
            "class": "App\\Profile\\Domain\\Profile",
            "events": ["App\\Profile\\Domain\\NameChanged"],
            "commands": ["App\\Profile\\Application\\ChangeName"]
        }
    },
    "events": {
        "App\\Profile\\Domain\\NameChanged": {
            "name": "profile.name_changed",
            "class": "App\\Profile\\Domain\\NameChanged"
        }
    },
    "commands": {
        "App\\Profile\\Application\\ChangeName": {
            "name": "ChangeName",
            "class": "App\\Profile\\Application\\ChangeName",
            "events": ["App\\Profile\\Domain\\NameChanged"],
            "projections": ["App\\Profile\\Domain\\ProfileName"]
        }
    },
    "subscribers": {},
    "projections": {
        "App\\Profile\\Domain\\ProfileName": {
            "name": "ProfileName",
            "class": "App\\Profile\\Domain\\ProfileName",
            "events": ["App\\Profile\\Domain\\NameChanged"]
        }
    },
    "userInterfaces": {}
}
```
A subscriber has a `type` of `subscriber`, `processor` or `projector`, and its `events` contain `*` if it subscribes
to all events. Aggregates, events and subscribers are named after their attribute, everything else after its short
class name. The collections and lists are sorted, so the output only changes when your domain changes.

:::tip
Commit the JSON output and compare it in your pipeline to spot unintended changes of your domain, like a command
that suddenly records another event.
:::

## Learn more

* [How the analyser detects your domain](analyser.md)
* [How to group your domain into bounded contexts](analyser.md#bounded-contexts)
* [How to configure the extension](getting-started.md#configuration)
