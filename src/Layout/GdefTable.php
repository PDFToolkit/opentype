<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class GdefTable
{
    /** @param list<Coverage> $markGlyphSets */
    public function __construct(public ClassDefinition $glyphClassDef = new ClassDefinition(), public ClassDefinition $markAttachClassDef = new ClassDefinition(), public array $markGlyphSets = [])
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
