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

    public function __construct(GlyphSequence $input, private readonly int $maximum)
    {
        $this->glyphs = $input->glyphs;
        $this->clusters = $input->clusters;
        $this->components = $input->componentClusters;
    }

    /**
     * @param non-empty-list<int> $positions
     * @param non-empty-list<GlyphId> $replacement
     */
    public function replace(array $positions, array $replacement): int
    {
        $delta = count($replacement) - count($positions);
        if (count($this->glyphs) + $delta > $this->maximum) {
            throw new MalformedFontException('Substitution expansion exceeds the output glyph budget.');
        }
        $cluster = $this->clusters[$positions[0]];
        $components = [];
        foreach ($positions as $position) {
            $cluster = min($cluster, $this->clusters[$position]);
            array_push($components, ...$this->components[$position]);
        }
        $first = $positions[0];
        foreach (array_reverse(array_slice($positions, 1)) as $position) {
            array_splice($this->glyphs, $position, 1);
            array_splice($this->clusters, $position, 1);
            array_splice($this->components, $position, 1);
        }
        array_splice($this->glyphs, $first, 1, $replacement);
        array_splice($this->clusters, $first, 1, array_fill(0, count($replacement), $cluster));
        array_splice($this->components, $first, 1, array_fill(0, count($replacement), $components));
        return $delta;
    }

    public function result(): GlyphSequence
    {
        return new GlyphSequence($this->glyphs, $this->clusters, $this->components);
    }
}
