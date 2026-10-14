<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class SequentialCharacterMap implements CharacterMapSubtable
{
    /** @param list<SequentialMapGroup> $groups */
    public function __construct(private array $groups)
    {
        if (!array_is_list($groups)) {
            throw new \InvalidArgumentException('Cmap groups require a list.');
        }
        $previousEnd = -1;
        foreach ($groups as $group) {
            if (!$group instanceof SequentialMapGroup) {
                throw new \InvalidArgumentException('A sequential cmap contains immutable SequentialMapGroup records.');
            }
            if ($group->startCharCode->value <= $previousEnd) {
                throw new \InvalidArgumentException('Sequential cmap groups must be sorted and must not overlap.');
            }
            $previousEnd = $group->endCharCode->value;
        }
    }

    public function format(): int
    {
        return 12;
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        $low = 0;
        $high = count($this->groups) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $group = $this->groups[$mid];
            if ($codePoint->value < $group->startCharCode->value) {
                $high = $mid - 1;
            } elseif ($codePoint->value > $group->endCharCode->value) {
                $low = $mid + 1;
            } else {
                return new GlyphId($group->startGlyphId->value + $codePoint->value - $group->startCharCode->value);
            }
        }

        return new GlyphId(0);
    }
}
