<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CoverageFormat2 implements Coverage
{
    /** @var list<RangeRecord> */
    public array $rangeRecords;
    private int $glyphCount;

    /** @param list<RangeRecord> $rangeRecords */
    public function __construct(array $rangeRecords)
    {
        $previousEnd = -1;
        $index = 0;
        foreach ($rangeRecords as $range) {
            if (!$range instanceof RangeRecord) {
                throw new \InvalidArgumentException('Coverage ranges must be RangeRecord objects.');
            }

            if ($range->startGlyphId->value <= $previousEnd || $range->startCoverageIndex !== $index) {
                throw new MalformedFontException('Coverage ranges must be ordered, disjoint and have consecutive indices.');
            }
            $previousEnd = $range->endGlyphId->value;
            $index += $range->count();
        }
        if ($index > 65536) {
            throw new MalformedFontException('Too many covered glyphs.');
        }
        $this->rangeRecords = array_values($rangeRecords);
        $this->glyphCount = $index;
    }

    public function indexOf(GlyphId $glyph): ?int
    {
        $low = 0;
        $high = count($this->rangeRecords) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $range = $this->rangeRecords[$middle];
            if ($glyph->value < $range->startGlyphId->value) {
                $high = $middle - 1;
            } elseif ($glyph->value > $range->endGlyphId->value) {
                $low = $middle + 1;
            } else {
                return $range->startCoverageIndex + $glyph->value - $range->startGlyphId->value;
            }
        }

        return null;
    }

    public function count(): int
    {
        return $this->glyphCount;
    }

    public function glyphs(): array
    {
        $result = [];
        foreach ($this->rangeRecords as $range) {
            for ($id = $range->startGlyphId->value; $id <= $range->endGlyphId->value; ++$id) {
                $result[] = new GlyphId($id);
            }
        }

        return $result;
    }
}
