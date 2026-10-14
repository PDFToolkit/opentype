<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class SegmentedCharacterMap implements CharacterMapSubtable
{
    /** @param list<Segment> $segments */
    public function __construct(private array $segments)
    {
        if ($segments === [] || !array_is_list($segments)) {
            throw new \InvalidArgumentException('A segmented cmap requires an ordered list ending with the 0xFFFF segment.');
        }
        $previousEnd = -1;
        foreach ($segments as $segment) {
            if (!$segment instanceof Segment) {
                throw new \InvalidArgumentException('A segmented cmap contains immutable Segment records.');
            }
            if ($segment->startCode <= $previousEnd) {
                throw new \InvalidArgumentException('Cmap segments must be sorted and must not overlap.');
            }
            $previousEnd = $segment->endCode;
        }
        $last = $segments[count($segments) - 1];
        if ($last->startCode !== 0xFFFF || $last->endCode !== 0xFFFF) {
            throw new \InvalidArgumentException('The final cmap segment must cover 0xFFFF.');
        }
    }

    public function format(): int
    {
        return 4;
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        $low = 0;
        $high = count($this->segments) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $segment = $this->segments[$mid];
            if ($codePoint->value < $segment->startCode) {
                $high = $mid - 1;
            } elseif ($codePoint->value > $segment->endCode) {
                $low = $mid + 1;
            } else {
                return new GlyphId($segment->glyphIndexFor($codePoint->value));
            }
        }

        return new GlyphId(0);
    }
}
