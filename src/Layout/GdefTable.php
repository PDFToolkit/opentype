<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class GdefTable
{
    /**
     * @param list<Coverage> $markGlyphSets
     * @param array<int, list<int>> $attachPoints
     * @param array<int, list<CaretValue>> $ligatureCarets
     */
    public function __construct(public ClassDefinition $glyphClassDef = new ClassDefinition(), public ClassDefinition $markAttachClassDef = new ClassDefinition(), public array $markGlyphSets = [], public array $attachPoints = [], public array $ligatureCarets = [], public ?\PDFToolkit\OpenType\Variation\ItemVariationStore $itemVariationStore = null)
    {
        foreach ($glyphClassDef->ranges as $range) {
            if ($range->classValue > 4) {
                throw new MalformedFontException('GDEF glyph classes must be zero through four.');
            }
        }
        foreach ($markGlyphSets as $set) {
            if (!$set instanceof Coverage) {
                throw new \InvalidArgumentException('GDEF mark sets contain Coverage objects.');
            }
        }
        if (!array_is_list($markGlyphSets)) {
            throw new \InvalidArgumentException('Mark filtering sets must retain font index order.');
        }
        foreach ($attachPoints as $glyph => $points) {
            if (!is_int($glyph) || $glyph < 0 || $glyph > 65535 || !array_is_list($points)) {
                throw new \InvalidArgumentException('Invalid GDEF attachment glyph or point list.');
            }
            $previous = -1;
            foreach ($points as $point) {
                if (!is_int($point) || $point <= $previous || $point > 65535) {
                    throw new \InvalidArgumentException('GDEF attachment points must be ordered unique unsigned indices.');
                }
                $previous = $point;
            }
        }
        foreach ($ligatureCarets as $glyph => $carets) {
            if (!is_int($glyph) || $glyph < 0 || $glyph > 65535 || !array_is_list($carets)) {
                throw new \InvalidArgumentException('Invalid GDEF ligature caret glyph or caret list.');
            }
            foreach ($carets as $caret) {
                if (!$caret instanceof CaretValue) {
                    throw new \InvalidArgumentException('Ligature carets require CaretValue objects.');
                }
            }
        }
    }

    public function ignores(GlyphId $glyph, LookupFlags $flags): bool
    {
        $class = $this->glyphClassDef->classOf($glyph);
        if (($class === 1 && ($flags->value & 2) !== 0) || ($class === 2 && ($flags->value & 4) !== 0)) {
            return true;
        }
        if ($class !== 3) {
            return false;
        }
        if (($flags->value & 8) !== 0) {
            return true;
        }
        if ($flags->markFilteringSet !== null) {
            $set = $this->markGlyphSets[$flags->markFilteringSet] ?? throw new MalformedFontException('A lookup refers to an absent GDEF mark filtering set.');
            return $set->indexOf($glyph) === null;
        }
        $attachment = ($flags->value >> 8) & 255;
        return $attachment !== 0 && $this->markAttachClassDef->classOf($glyph) !== $attachment;
    }
}
