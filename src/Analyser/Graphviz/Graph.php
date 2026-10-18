<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Graphviz;

use function implode;
use function is_bool;
use function sprintf;
use function str_repeat;
use function str_replace;

/**
 * A minimal writer for directed graphs in the DOT language of Graphviz.
 *
 * @phpstan-type Attributes array<string, string|int|float|bool>
 */
final class Graph
{
    /** @var Attributes */
    private array $attributes = [];

    /** @var Attributes */
    private array $nodeDefaults = [];

    /** @var Attributes */
    private array $edgeDefaults = [];

    /** @var array<string, Attributes> */
    private array $nodes = [];

    /** @var list<array{string, string, Attributes}> */
    private array $edges = [];

    /** @var list<Graph> */
    private array $subgraphs = [];

    public function __construct(
        private readonly string $id = 'G',
        private readonly bool $isSubgraph = false,
    ) {
    }

    public function set(string $name, string|int|float|bool $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /** @param Attributes $attributes */
    public function nodeDefaults(array $attributes): self
    {
        $this->nodeDefaults = $attributes;

        return $this;
    }

    /** @param Attributes $attributes */
    public function edgeDefaults(array $attributes): self
    {
        $this->edgeDefaults = $attributes;

        return $this;
    }

    /** @param Attributes $attributes */
    public function node(string $id, array $attributes = []): self
    {
        $this->nodes[$id] = $attributes;

        return $this;
    }

    /** @param Attributes $attributes */
    public function edge(string $from, string $to, array $attributes = []): self
    {
        $this->edges[] = [$from, $to, $attributes];

        return $this;
    }

    public function subgraph(string $id): self
    {
        $subgraph = new self($id, true);
        $this->subgraphs[] = $subgraph;

        return $subgraph;
    }

    public function render(): string
    {
        return $this->renderGraph(0) . "\n";
    }

    private function renderGraph(int $depth): string
    {
        $indent = str_repeat('    ', $depth + 1);
        $lines = [];

        foreach ($this->attributes as $name => $value) {
            $lines[] = sprintf('%s%s=%s;', $indent, $name, self::quote($value));
        }

        if ($this->nodeDefaults !== []) {
            $lines[] = sprintf('%snode %s;', $indent, self::attributeList($this->nodeDefaults));
        }

        if ($this->edgeDefaults !== []) {
            $lines[] = sprintf('%sedge %s;', $indent, self::attributeList($this->edgeDefaults));
        }

        foreach ($this->subgraphs as $subgraph) {
            $lines[] = $indent . $subgraph->renderGraph($depth + 1);
        }

        foreach ($this->nodes as $id => $attributes) {
            $lines[] = sprintf('%s%s%s;', $indent, self::quote($id), self::attributeSuffix($attributes));
        }

        foreach ($this->edges as [$from, $to, $attributes]) {
            $lines[] = sprintf(
                '%s%s -> %s%s;',
                $indent,
                self::quote($from),
                self::quote($to),
                self::attributeSuffix($attributes),
            );
        }

        $head = $this->isSubgraph
            ? sprintf('subgraph %s {', self::quote($this->id))
            : sprintf('digraph %s {', self::quote($this->id));

        if ($lines === []) {
            return $head . '}';
        }

        return $head . "\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . '}';
    }

    /** @param Attributes $attributes */
    private static function attributeSuffix(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        return ' ' . self::attributeList($attributes);
    }

    /** @param Attributes $attributes */
    private static function attributeList(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            $parts[] = sprintf('%s=%s', $name, self::quote($value));
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private static function quote(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return '"' . str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], (string)$value) . '"';
    }
}
