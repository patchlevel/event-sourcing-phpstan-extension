<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Tests\Unit\Analyser\Graphviz;

use Patchlevel\EventSourcingPHPStanExtension\Analyser\Graphviz\Graph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Graph::class)]
final class GraphTest extends TestCase
{
    public function testEmptyGraph(): void
    {
        self::assertSame("digraph \"G\" {}\n", (new Graph())->render());
    }

    public function testGraph(): void
    {
        $graph = (new Graph('domain'))
            ->set('rankdir', 'LR')
            ->set('ranksep', 1)
            ->nodeDefaults(['shape' => 'box', 'fixedsize' => true])
            ->edgeDefaults(['color' => 'red'])
            ->node('a', ['label' => 'A'])
            ->node('b')
            ->edge('a', 'b')
            ->edge('b', 'a', ['style' => 'dashed']);

        $graph->subgraph('cluster_x')
            ->set('label', 'X')
            ->node('c');

        $graph->subgraph('cluster_empty');

        self::assertSame(
            <<<'DOT'
                digraph "domain" {
                    rankdir="LR";
                    ranksep="1";
                    node [shape="box", fixedsize="true"];
                    edge [color="red"];
                    subgraph "cluster_x" {
                        label="X";
                        "c";
                    }
                    subgraph "cluster_empty" {}
                    "a" [label="A"];
                    "b";
                    "a" -> "b";
                    "b" -> "a" [style="dashed"];
                }

                DOT,
            $graph->render(),
        );
    }

    public function testNestedSubgraphsAreIndented(): void
    {
        $graph = new Graph();
        $graph->subgraph('outer')->subgraph('inner')->node('a');

        self::assertSame(
            <<<'DOT'
                digraph "G" {
                    subgraph "outer" {
                        subgraph "inner" {
                            "a";
                        }
                    }
                }

                DOT,
            $graph->render(),
        );
    }

    public function testEscaping(): void
    {
        $graph = (new Graph())
            ->node('App\Domain\Profile', ['label' => "say \"hello\"\nworld", 'fixedsize' => false]);

        self::assertSame(
            <<<'DOT'
                digraph "G" {
                    "App\\Domain\\Profile" [label="say \"hello\"\nworld", fixedsize="false"];
                }

                DOT,
            $graph->render(),
        );
    }
}
