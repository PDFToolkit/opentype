<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;

/** Unhinted recursive component assembly, separate from glyph parsing and bytecode. */
final readonly class TrueTypeOutlineEngine
{
    public function __construct(private CompositeOffsetPolicy $offsetPolicy, private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function outline(TrueTypeOutlines $font, GlyphId $glyph): QuadraticOutline
    {
        return $this->expand($font, $glyph, [], new LayoutReadBudget($this->limits->maxOperations), new LayoutReadBudget($this->limits->maxExpandedPoints));
    }

    /** @param array<int, true> $ancestors */
    private function expand(TrueTypeOutlines $font, GlyphId $glyph, array $ancestors, LayoutReadBudget $operations, LayoutReadBudget $pointBudget): QuadraticOutline
    {
        if (isset($ancestors[$glyph->value]) || count($ancestors) >= $this->limits->maxDepth) {
            throw new MalformedFontException('A composite outline contains a cycle or exceeds its depth budget.');
        }
        $operations->consume(1);
        $ancestors[$glyph->value] = true;
        $description = $font->glyph($glyph);
        if ($description instanceof EmptyGlyph) {
            return new QuadraticOutline([], []);
        }
        if ($description instanceof SimpleGlyph) {
            $pointBudget->consume(count($description->outline->points));

            return $description->outline;
        }
        if (!$description instanceof CompositeGlyph) {
            throw new UnsupportedFontException('Unknown glyph description implementation.');
        }
        $points = [];
        $ends = [];
        foreach ($description->components as $component) {
            $outline = $this->expand($font, $component->glyph, $ancestors, $operations, $pointBudget);
            $operations->consume(count($outline->points) + count($outline->endPoints));
            $transformed = array_map($component->transform->apply(...), $outline->points);
            if ($component->usesOffsets()) {
                $offset = new OutlinePoint($component->argument1, $component->argument2);
                $scaled = ($component->flags & 0x0800) !== 0;
                if (($component->flags & 0x1800) === 0) {
                    if ($this->offsetPolicy === CompositeOffsetPolicy::RejectAmbiguous && $component->transform != new ComponentTransform()) {
                        throw new UnsupportedFontException('Component offset scaling requires an explicit policy.');
                    }
                    $scaled = $this->offsetPolicy === CompositeOffsetPolicy::Scaled;
                }
                if ($scaled) {
                    $offset = $component->transform->apply($offset);
                }
                // ROUND_XY_TO_GRID belongs to scaled, grid-fitted execution. This
                // engine deliberately retains unhinted design-unit fractions.
            } else {
                $parent = $points[$component->argument1] ?? throw new MalformedFontException('A component parent point is unavailable.');
                $child = $transformed[$component->argument2] ?? throw new MalformedFontException('A component child point is unavailable.');
                $offset = new OutlinePoint($parent->x - $child->x, $parent->y - $child->y);
            }
            $start = count($points);
            foreach ($transformed as $point) {
                $points[] = new OutlinePoint($point->x + $offset->x, $point->y + $offset->y, $point->onCurve);
            }
            foreach ($outline->endPoints as $end) {
                $ends[] = $start + $end;
            }
        }

        return new QuadraticOutline($points, $ends);
    }
}
