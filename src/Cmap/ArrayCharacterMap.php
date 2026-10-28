<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

/** A bounded consecutive character range; a zero glyph remains .notdef. */
final readonly class ArrayCharacterMap implements CharacterMapSubtable
{
    /** @param list<int> $glyphs */
    public function __construct(private int $tableFormat, private int $firstCode, private array $glyphs)
    {
        if (!in_array($tableFormat, [0, 2, 6, 10], true) || !array_is_list($glyphs)
            || $firstCode < 0 || $firstCode > 0x10FFFF || count($glyphs) > 0x110000 - $firstCode
            || ($tableFormat !== 10 && $firstCode + count($glyphs) > 0x10000)
        ) {
            throw new \InvalidArgumentException('Invalid array cmap format or character range.');
        }
        foreach ($glyphs as $glyph) {
            if (!is_int($glyph) || $glyph < 0 || $glyph > 65535) {
                throw new \InvalidArgumentException('Array cmap glyphs are unsigned 16-bit indices.');
            }
        }
    }

    public function format(): int
    {
        return $this->tableFormat;
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        return new GlyphId($this->glyphs[$codePoint->value - $this->firstCode] ?? 0);
    }
}
