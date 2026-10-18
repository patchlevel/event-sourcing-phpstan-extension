<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser;

use Patchlevel\EventSourcingPHPStanExtension\Analyser\Graphviz\Graph;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\Project;
use Patchlevel\EventSourcingPHPStanExtension\Analyser\Model\SubscriberType;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

use function array_diff;
use function array_keys;
use function array_merge;
use function in_array;

/**
 * Renders the model as a diagram inspired by Event Storming in the DOT language of Graphviz.
 */
final class GraphvizFormatter implements ErrorFormatter
{
    private const FONT_COLOR = '#343a40';
    private const EDGE_COLOR = '#343a40';
    private const AGGREGATE_COLOR = '#ffec99';
    private const EVENT_COLOR = '#ffc078';
    private const COMMAND_COLOR = '#74c0fc';
    private const SUBSCRIBER_COLOR = '#ffa8a8';
    private const PROCESSOR_COLOR = '#e599f7';
    private const PROJECTOR_COLOR = '#8ce99a';
    private const PROJECTION_COLOR = '#d8f5a2';
    private const USER_INTERFACE_COLOR = '#dee2e6';

    public function __construct(
        private readonly bool $enabled,
        private readonly ErrorFormatter $fallbackFormatter,
    ) {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if (!$this->enabled) {
            $output->getStyle()->error('The analyser is disabled. Enable it with the "patchlevelEventSourcing.analyser" parameter.');

            return 1;
        }

        // without a complete analysis the model would be incomplete, so the internal errors are shown instead
        if ($analysisResult->hasInternalErrors()) {
            return $this->fallbackFormatter->formatErrors($analysisResult, $output);
        }

        $project = (new ProjectFactory())->create($analysisResult->getCollectedData());

        $output->writeRaw($this->graph($project)->render());

        return 0;
    }

    private function graph(Project $project): Graph
    {
        $graph = (new Graph())
            ->set('rankdir', 'LR')
            ->set('nodesep', '0.5')
            ->set('ranksep', '1')
            ->set('fontcolor', self::FONT_COLOR)
            ->nodeDefaults([
                'shape' => 'Mrecord',
                'style' => 'filled',
                'width' => 3,
                'height' => 1,
                'fixedsize' => true,
                'fontcolor' => self::FONT_COLOR,
            ])
            ->edgeDefaults(['color' => self::EDGE_COLOR]);

        $grouped = [];

        foreach ($project->boundedContexts as $boundedContext) {
            $subgraph = $graph->subgraph('cluster_context_' . $boundedContext->name)
                ->set('label', $boundedContext->name)
                ->set('style', 'dotted');

            $this->nodes(
                $subgraph,
                $project,
                $boundedContext->aggregates,
                array_merge(
                    $boundedContext->events,
                    $boundedContext->commands,
                    $boundedContext->subscribers,
                    $boundedContext->projections,
                    $boundedContext->userInterfaces,
                ),
            );

            $grouped = array_merge(
                $grouped,
                $boundedContext->aggregates,
                $boundedContext->events,
                $boundedContext->commands,
                $boundedContext->subscribers,
                $boundedContext->projections,
                $boundedContext->userInterfaces,
            );
        }

        // everything outside of a bounded context is drawn on the top level
        $this->nodes(
            $graph,
            $project,
            array_diff(array_keys($project->aggregates), $grouped),
            array_diff(
                array_merge(
                    array_keys($project->events),
                    array_keys($project->commands),
                    array_keys($project->subscribers),
                    array_keys($project->projections),
                    array_keys($project->userInterfaces),
                ),
                $grouped,
            ),
        );

        $this->edges($graph, $project);

        return $graph;
    }

    /**
     * Draws the aggregates as clusters around their commands and events, and all other classes as plain nodes.
     *
     * @param array<string> $aggregates
     * @param array<string> $classes
     */
    private function nodes(Graph $graph, Project $project, array $aggregates, array $classes): void
    {
        $drawn = [];

        foreach ($aggregates as $aggregateClass) {
            $aggregate = $project->aggregates[$aggregateClass];

            $subgraph = $graph->subgraph('cluster_aggregate_' . $aggregate->class)
                ->set('label', $aggregate->name)
                ->set('style', 'filled')
                ->set('color', self::AGGREGATE_COLOR)
                ->set('penwidth', 0);

            foreach (array_merge($aggregate->commands, $aggregate->events) as $class) {
                // an element of another bounded context stays in its own context
                if (!in_array($class, $classes, true)) {
                    continue;
                }

                $this->node($subgraph, $project, $class);
                $drawn[] = $class;
            }
        }

        foreach (array_diff($classes, $drawn) as $class) {
            $this->node($graph, $project, $class);
        }
    }

    private function node(Graph $graph, Project $project, string $class): void
    {
        if (isset($project->events[$class])) {
            $graph->node($class, ['label' => $project->events[$class]->name, 'color' => self::EVENT_COLOR]);

            return;
        }

        if (isset($project->commands[$class])) {
            $graph->node($class, ['label' => $project->commands[$class]->name, 'color' => self::COMMAND_COLOR]);

            return;
        }

        if (isset($project->subscribers[$class])) {
            $subscriber = $project->subscribers[$class];

            $graph->node($class, [
                'label' => in_array('*', $subscriber->events, true) ? $subscriber->name . "\n(all events)" : $subscriber->name,
                'color' => match ($subscriber->type) {
                    SubscriberType::Subscriber => self::SUBSCRIBER_COLOR,
                    SubscriberType::Processor => self::PROCESSOR_COLOR,
                    SubscriberType::Projector => self::PROJECTOR_COLOR,
                },
            ]);

            return;
        }

        if (isset($project->projections[$class])) {
            $graph->node($class, ['label' => $project->projections[$class]->name, 'color' => self::PROJECTION_COLOR]);

            return;
        }

        if (!isset($project->userInterfaces[$class])) {
            return;
        }

        $graph->node($class, ['label' => $project->userInterfaces[$class]->name, 'color' => self::USER_INTERFACE_COLOR]);
    }

    private function edges(Graph $graph, Project $project): void
    {
        foreach ($project->commands as $command) {
            foreach ($command->events as $event) {
                $graph->edge($command->class, $event);
            }
        }

        foreach ($project->projections as $projection) {
            foreach ($projection->events as $event) {
                $graph->edge($event, $projection->class);
            }
        }

        foreach ($project->commands as $command) {
            foreach ($command->projections as $projection) {
                $graph->edge($projection, $command->class);
            }
        }

        foreach ($project->subscribers as $subscriber) {
            foreach ($subscriber->events as $event) {
                if ($event === '*') {
                    continue;
                }

                $graph->edge($event, $subscriber->class);
            }

            foreach ($subscriber->commands as $command) {
                $graph->edge($subscriber->class, $command);
            }
        }

        foreach ($project->userInterfaces as $userInterface) {
            foreach ($userInterface->commands as $command) {
                $graph->edge($userInterface->class, $command);
            }

            foreach ($userInterface->subscribers as $subscriber) {
                $graph->edge($subscriber, $userInterface->class);
            }
        }
    }
}
