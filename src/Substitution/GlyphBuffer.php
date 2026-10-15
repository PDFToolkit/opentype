<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

/** @internal Mutable state belongs to exactly one explicit substitution execution. */
final class GlyphBuffer
{
    /** @var list<GlyphId> */
    public array $glyphs;
    /** @var list<int> */
    public array $clusters;
    /** @var list<non-empty-list<int>> */
    public array $components;
    /** @var list<LigatureAssociation|null> */
    public array $associations;
    private int $nextLigatureId = 1;

    public function __construct(GlyphSequence $input, private readonly int $maximum)
    {
        $this->glyphs = $input->glyphs;
        $this->clusters = $input->clusters;
        $this->components = $input->componentClusters;
        $this->associations = $input->ligatureAssociations;
        foreach ($this->associations as $association) {
            if ($association !== null) {
                $this->nextLigatureId = max($this->nextLigatureId, $association->ligatureId + 1);
            }
        }
    }

    /**
     * @param non-empty-list<int> $positions
     * @param non-empty-list<GlyphId> $replacement
     */
    public function replace(array $positions, array $replacement, bool $ligature = false, ?LayoutReadBudget $budget = null, ?\PDFToolkit\OpenType\Layout\GdefTable $definition = null): int
    {
        $delta = count($replacement) - count($positions);
        if (count($this->glyphs) + $delta > $this->maximum) {
            throw new MalformedFontException('Substitution expansion exceeds the output glyph budget.');
        }
        $cluster = $this->clusters[$positions[0]];
        $components = [];
        $componentOffsets = [];
        $oldLigatures = [];
        foreach ($positions as $position) {
            $componentOffsets[$position] = count($components);
            $oldAssociation = $this->associations[$position];
            if ($oldAssociation !== null && $oldAssociation->componentIndex === null) {
                $oldLigatures[$oldAssociation->ligatureId] = count($components);
            }
            $cluster = min($cluster, $this->clusters[$position]);
            array_push($components, ...$this->components[$position]);
        }
        $first = $positions[0];
        $association = $this->associations[$first];
        if ($ligature) {
            $identity = $this->nextLigatureId++;
            $association = new LigatureAssociation($identity);
            $last = $positions[count($positions) - 1];
            $component = 0;
            $consumed = array_fill_keys($positions, true);
            for ($index = $first; $index <= $last; ++$index) {
                $budget?->consume(1);
                if (isset($componentOffsets[$index])) {
                    $component = $componentOffsets[$index] + count($this->components[$index]) - 1;
                } elseif (!isset($consumed[$index])) {
                    $old = $this->associations[$index];
                    $markComponent = $old !== null && $old->componentIndex !== null && isset($oldLigatures[$old->ligatureId]) ? $oldLigatures[$old->ligatureId] + $old->componentIndex : $component;
                    array_splice($this->associations, $index, 1, [new LigatureAssociation($identity, $markComponent)]);
                }
            }
            if ($oldLigatures !== []) {
                foreach ($this->associations as $index => $old) {
                    $budget?->consume(1);
                    if ($old !== null && $old->componentIndex !== null && isset($oldLigatures[$old->ligatureId])) {
                        array_splice($this->associations, $index, 1, [new LigatureAssociation($identity, $oldLigatures[$old->ligatureId] + $old->componentIndex)]);
                    }
                }
            }
            for ($index = $last + 1; isset($this->glyphs[$index]) && $definition?->glyphClassDef->classOf($this->glyphs[$index]) === 3; ++$index) {
                $budget?->consume(1);
                if ($this->associations[$index] === null) {
                    array_splice($this->associations, $index, 1, [new LigatureAssociation($identity, count($components) - 1)]);
                }
            }
        } elseif (count($replacement) > 1 && $association?->componentIndex === null) {
            $association = null;
        }
        foreach (array_reverse(array_slice($positions, 1)) as $position) {
            array_splice($this->glyphs, $position, 1);
            array_splice($this->clusters, $position, 1);
            array_splice($this->components, $position, 1);
            array_splice($this->associations, $position, 1);
        }
        array_splice($this->glyphs, $first, 1, $replacement);
        array_splice($this->clusters, $first, 1, array_fill(0, count($replacement), $cluster));
        array_splice($this->components, $first, 1, array_fill(0, count($replacement), $components));
        array_splice($this->associations, $first, 1, array_fill(0, count($replacement), $association));
        return $delta;
    }

    public function result(): GlyphSequence
    {
        return new GlyphSequence($this->glyphs, $this->clusters, $this->components, $this->associations);
    }
}
