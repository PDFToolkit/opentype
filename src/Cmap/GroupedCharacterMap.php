<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

/** Compact format 8 sequential or format 13 constant groups, never expanded per scalar. */
final readonly class GroupedCharacterMap implements CharacterMapSubtable
{
    /** @param list<CharacterMapGroup> $groups */
    public function __construct(private int $tableFormat, private array $groups)
    {
        if (!in_array($tableFormat, [8, 13], true) || !array_is_list($groups)) {
            throw new \InvalidArgumentException('Invalid grouped cmap format or list.');
        }
        $previous = -1;
        foreach ($groups as $group) {
            if (!$group instanceof CharacterMapGroup || $group->start <= $previous
                || ($tableFormat === 8 && ($group->glyph->value + $group->end - $group->start > 65535
                    || ($group->start <= 0xDFFF && $group->end >= 0xD800)))
            ) {
                throw new \InvalidArgumentException('Cmap groups must be ordered, disjoint and contain valid glyph indices.');
            }
            $previous = $group->end;
        }
    }

    public function format(): int
    {
        return $this->tableFormat;
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        $low = 0;
        $high = count($this->groups) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $group = $this->groups[$mid];
            if ($codePoint->value < $group->start) {
                $high = $mid - 1;
            } elseif ($codePoint->value > $group->end) {
                $low = $mid + 1;
            } else {
                return new GlyphId($group->glyph->value + ($this->tableFormat === 8 ? $codePoint->value - $group->start : 0));
            }
        }

        return new GlyphId(0);
    }
}
