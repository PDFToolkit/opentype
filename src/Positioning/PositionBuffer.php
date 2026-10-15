<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** @internal Mutable positions and attachment graph belong to one explicit execution. */
final class PositionBuffer
{
    /** @var list<GlyphPosition> */
    public array $positions;
    /** @var array<int, AttachmentLink> */
    public array $attachments = [];

    public function __construct(public readonly PositionedGlyphSequence $input)
    {
        $this->positions = $input->positions;
    }

    public function set(int $index, GlyphPosition $position): void
    {
        if (!isset($this->positions[$index])) {
            throw new MalformedFontException('A position update refers outside its glyph sequence.');
        }
        array_splice($this->positions, $index, 1, [$position]);
    }

    public function advanceBetween(int $from, int $to, bool $vertical, LayoutReadBudget $budget): float
    {
        $advance = 0.0;
        for ($i = min($from, $to); $i < max($from, $to); ++$i) {
            $budget->consume(1);
            $advance += $vertical ? $this->positions[$i]->yAdvance : $this->positions[$i]->xAdvance;
        }
        return $from <= $to ? $advance : -$advance;
    }

    public function result(LayoutReadBudget $budget, int $maxDepth): PositionedGlyphSequence
    {
        $resolved = [];
        foreach (array_keys($this->attachments) as $index) {
            $path = [];
            $visiting = [];
            $cursor = $index;
            while (!isset($resolved[$cursor]) && isset($this->attachments[$cursor])) {
                $budget->consume(1);
                if (count($path) >= $maxDepth || isset($visiting[$cursor])) {
                    throw new MalformedFontException('The positioning attachment graph is cyclic or exceeds its depth budget.');
                }
                $visiting[$cursor] = true;
                $path[] = $cursor;
                $cursor = $this->attachments[$cursor]->parent;
            }
            foreach (array_reverse($path) as $child) {
                $link = $this->attachments[$child];
                $current = $this->positions[$child];
                $parent = $this->positions[$link->parent];
                $x = $link->adjustX ? $parent->xOffset + $link->xDifference - $this->advanceBetween($link->parent, $child, false, $budget) : $current->xOffset;
                $y = $link->adjustY ? $parent->yOffset + $link->yDifference - $this->advanceBetween($link->parent, $child, true, $budget) : $current->yOffset;
                $this->set($child, new GlyphPosition($current->xAdvance, $current->yAdvance, $x, $y));
                $resolved[$child] = true;
            }
        }
        return new PositionedGlyphSequence($this->input->sequence, $this->positions);
    }
}
