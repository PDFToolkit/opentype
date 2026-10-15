<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class GvarInstance
{
    public function __construct(public GvarTable $table, public NormalizedCoordinates $coordinates, private OutlineLimits $limits = new OutlineLimits())
    {
        if (count($coordinates->values) !== $table->axisCount) {
            throw new \InvalidArgumentException('gvar instance coordinates must match every axis.');
        }
    }

    /** Point sequence includes four phantom points; contour endpoints never include them.
     * @param list<OutlinePoint> $points
     * @param list<int> $contourEnds Empty for composite pseudo-points.
     * @return list<TupleDelta>
     */
    public function deltas(GlyphId $glyph, array $points, array $contourEnds, ?LayoutReadBudget $budget = null): array
    {
        $count = count($points);
        if (!array_is_list($points) || $count < 4 || $count > $this->limits->maxExpandedPoints || !array_is_list($contourEnds)) {
            throw new \InvalidArgumentException('gvar requires a bounded point list with four phantom points.');
        }
        $previous = -1;
        foreach ($contourEnds as $end) {
            if ($end <= $previous || $end >= $count - 4) {
                throw new \InvalidArgumentException('gvar contour endpoints must precede phantom points.');
            }
            $previous = $end;
        }
        $budget ??= new LayoutReadBudget($this->limits->maxOperations);
        $budget->consume($count);
        $x = $y = array_fill(0, $count, 0.0);
        foreach ($this->table->glyph($glyph) as $variation) {
            $budget->consume(1);
            $scalar = $variation->region->scalar($this->coordinates);
            if ($scalar == 0) {
                continue;
            }
            $budget->consume($count * 2);
            $values = array_fill(0, $count, null);
            foreach ($variation->deltas as $at => $delta) {
                $point = $variation->points[$at] ?? $at;
                if ($point >= $count) {
                    throw new MalformedFontException('A gvar delta exceeds its supplied point sequence.');
                }
                $values[$point] = $delta;
            }
            if ($variation->points === null && count($variation->deltas) !== $count) {
                throw new MalformedFontException('An all-point gvar tuple must match its target.');
            }
            $start = 0;
            foreach ($contourEnds as $end) {
                $referenced = [];
                for ($point = $start; $point <= $end; ++$point) {
                    if ($values[$point] !== null) {
                        $referenced[] = $point;
                    }
                }
                foreach ($referenced as $at => $first) {
                    $last = $referenced[$at + 1] ?? $referenced[0];
                    $a = $points[$first];
                    $b = $points[$last];
                    $da = $values[$first];
                    $db = $values[$last];
                    if ($da === null || $db === null) {
                        throw new \LogicException('Referenced gvar points require deltas.');
                    }
                    $point = $first === $end ? $start : $first + 1;
                    while ($point !== $last) {
                        $p = $points[$point];
                        $values[$point] = new TupleDelta($this->interpolate($p->x, $a->x, $b->x, $da->x, $db->x), $this->interpolate($p->y, $a->y, $b->y, $da->y, $db->y));
                        $point = $point === $end ? $start : $point + 1;
                    }
                }
                $start = $end + 1;
            }
            foreach ($values as $at => $value) {
                if ($value !== null) {
                    $x[$at] += $value->x * $scalar;
                    $y[$at] += $value->y * $scalar;
                }
            }
        }
        $result = [];
        foreach ($x as $at => $value) {
            $result[] = new TupleDelta($value, $y[$at]);
        }

        return $result;
    }

    private function interpolate(float $value, float $a, float $b, float $da, float $db): float
    {
        if ($a === $b) {
            return $da === $db ? $da : 0.0;
        }
        if ($a > $b) {
            [$a, $b, $da, $db] = [$b, $a, $db, $da];
        }
        if ($value <= $a) {
            return $da;
        }
        if ($value >= $b) {
            return $db;
        }

        return $da + ($db - $da) * ($value - $a) / ($b - $a);
    }
}
