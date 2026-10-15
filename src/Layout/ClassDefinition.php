<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

/** Glyphs not explicitly assigned to a class belong to class zero. */
final readonly class ClassDefinition
{
    /** @var list<ClassRange> */
    public array $ranges;

    /** @param list<ClassRange> $ranges */
    public function __construct(array $ranges = [])
    {
        $previous = -1;
        foreach ($ranges as $range) {
            if (!$range instanceof ClassRange || $range->startGlyph <= $previous) {
                throw new MalformedFontException('Glyph class ranges must be ordered and disjoint.');
            }
            $previous = $range->endGlyph;
        }
        $this->ranges = array_values($ranges);
    }

    public function classOf(GlyphId $glyph): int
    {
        $low = 0;
        $high = count($this->ranges) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $range = $this->ranges[$middle];
            if ($glyph->value < $range->startGlyph) {
                $high = $middle - 1;
            } elseif ($glyph->value > $range->endGlyph) {
                $low = $middle + 1;
            } else {
                return $range->classValue;
            }
        }
        return 0;
    }
}
