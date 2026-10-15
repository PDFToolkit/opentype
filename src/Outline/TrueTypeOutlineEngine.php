<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\GvarInstance;

/** Unhinted recursive component assembly, separate from glyph parsing and bytecode. */
final readonly class TrueTypeOutlineEngine
{
    public function __construct(private CompositeOffsetPolicy $offsetPolicy, private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function outline(TrueTypeOutlines $font, GlyphId $glyph, ?GvarInstance $variation = null): QuadraticOutline
    {
        return $this->glyph($font, $glyph, null, $variation)->outline;
    }

    public function glyph(TrueTypeOutlines $font, GlyphId $glyph, ?PhantomPointProvider $phantomProvider = null, ?GvarInstance $variation = null): TrueTypeGlyphOutline
    {
        if ($variation !== null && count($variation->table->glyphVariations) !== count($font->glyphs)) {
            throw new \InvalidArgumentException('gvar and outline glyph counts must match.');
        }

        return $this->expand($font, $glyph, [], new LayoutReadBudget($this->limits->maxOperations), new LayoutReadBudget($this->limits->maxExpandedPoints), $phantomProvider, $variation);
    }

    /** @param array<int, true> $ancestors */
    private function expand(TrueTypeOutlines $font, GlyphId $glyph, array $ancestors, LayoutReadBudget $operations, LayoutReadBudget $pointBudget, ?PhantomPointProvider $phantomProvider, ?GvarInstance $variation): TrueTypeGlyphOutline
    {
        if (isset($ancestors[$glyph->value]) || count($ancestors) >= $this->limits->maxDepth) {
            throw new MalformedFontException('A composite outline contains a cycle or exceeds its depth budget.');
        }
        $operations->consume(1);
        $ancestors[$glyph->value] = true;
        $description = $font->glyph($glyph);
        $phantoms = $phantomProvider?->points($glyph, $description);
        $defaultPhantoms = $phantoms?->points() ?? array_fill(0, 4, new OutlinePoint(0, 0));
        $basePoints = $description instanceof SimpleGlyph ? $description->outline->points : ($description instanceof CompositeGlyph ? array_map(fn (GlyphComponent $component) => new OutlinePoint($component->usesOffsets() ? $component->argument1 : 0, $component->usesOffsets() ? $component->argument2 : 0), $description->components) : []);
        $deltas = $variation?->deltas($glyph, array_merge($basePoints, $defaultPhantoms), $description instanceof SimpleGlyph ? $description->outline->endPoints : [], $operations);
        if ($deltas !== null && $phantoms !== null) {
            $at = count($basePoints);
            $phantoms = new PhantomPoints(new OutlinePoint($phantoms->left->x + $deltas[$at]->x, 0), new OutlinePoint($phantoms->right->x + $deltas[$at + 1]->x, 0), new OutlinePoint(0, $phantoms->top->y + $deltas[$at + 2]->y), new OutlinePoint(0, $phantoms->bottom->y + $deltas[$at + 3]->y));
        }
        if ($description instanceof EmptyGlyph) {
            return new TrueTypeGlyphOutline(new QuadraticOutline([], []), $phantoms);
        }
        if ($description instanceof SimpleGlyph) {
            $pointBudget->consume(count($description->outline->points));
            $points = [];
            foreach ($description->outline->points as $index => $point) {
                $points[] = new OutlinePoint($point->x + ($deltas[$index]->x ?? 0), $point->y + ($deltas[$index]->y ?? 0), $point->onCurve);
            }

            return new TrueTypeGlyphOutline(new QuadraticOutline($points, $description->outline->endPoints), $phantoms);
        }
        if (!$description instanceof CompositeGlyph) {
            throw new UnsupportedFontException('Unknown glyph description implementation.');
        }
        $points = [];
        $ends = [];
        foreach ($description->components as $componentIndex => $component) {
            $childGlyph = $this->expand($font, $component->glyph, $ancestors, $operations, $pointBudget, $phantomProvider, $variation);
            $outline = $childGlyph->outline;
            $operations->consume(count($outline->points) + count($outline->endPoints));
            $transformed = array_map($component->transform->apply(...), $outline->points);
            if ($component->usesOffsets()) {
                $offset = new OutlinePoint($component->argument1 + ($deltas[$componentIndex]->x ?? 0), $component->argument2 + ($deltas[$componentIndex]->y ?? 0));
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
                $parentPoints = array_merge($points, $phantoms?->points() ?? []);
                $childPoints = array_merge($transformed, array_map($component->transform->apply(...), $childGlyph->phantoms?->points() ?? []));
                $parent = $parentPoints[$component->argument1] ?? throw new MalformedFontException('A component parent point is unavailable.');
                $child = $childPoints[$component->argument2] ?? throw new MalformedFontException('A component child point is unavailable.');
                $offset = new OutlinePoint($parent->x - $child->x, $parent->y - $child->y);
            }
            if ($component->useMyMetrics() && $childGlyph->phantoms !== null) {
                $phantoms = $childGlyph->phantoms;
            }
            $start = count($points);
            foreach ($transformed as $point) {
                $points[] = new OutlinePoint($point->x + $offset->x, $point->y + $offset->y, $point->onCurve);
            }
            foreach ($outline->endPoints as $end) {
                $ends[] = $start + $end;
            }
        }

        return new TrueTypeGlyphOutline(new QuadraticOutline($points, $ends), $phantoms);
    }
}
