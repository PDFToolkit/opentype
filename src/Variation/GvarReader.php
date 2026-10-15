<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\CompositeGlyph;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\SimpleGlyph;
use PDFToolkit\OpenType\Outline\TrueTypeOutlines;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

final readonly class GvarReader
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function read(SfntFont $font, TrueTypeOutlines $outlines): GvarTable
    {
        if ($font->version !== SfntVersion::TrueType) {
            throw new UnsupportedFontException('gvar requires TrueType outlines.');
        }
        $table = $font->table(new Tag('gvar'));
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only gvar version 1.0 is supported.');
        }
        $axes = $table->uint16(4);
        $sharedCount = $table->uint16(6);
        $sharedAt = $table->uint32(8);
        $glyphs = $table->uint16(12);
        $flags = $table->uint16(14);
        $dataAt = $table->uint32(16);
        $width = ($flags & 1) !== 0 ? 4 : 2;
        $header = 20 + ($glyphs + 1) * $width;
        if ($table->size() > $this->limits->maxBytes || $flags > 1 || $sharedCount > 4096 || $axes !== count((new FvarReader())->read($font->table(new Tag('fvar')))->axes) || $glyphs !== $font->glyphCount() || $glyphs !== count($outlines->glyphs) || $dataAt < $header || ($sharedCount > 0 && ($sharedAt < $header || $sharedAt + $sharedCount * $axes * 2 > $dataAt))) {
            throw new MalformedFontException('Invalid gvar dimensions, offsets or budget.');
        }
        $budget = new LayoutReadBudget($this->limits->maxRecords);
        $budget->consume($glyphs + $sharedCount);
        $tuples = new TupleVariationReader();
        $shared = [];
        for ($index = 0; $index < $sharedCount; ++$index) {
            $shared[] = $tuples->tuple($table, $sharedAt, $axes, $budget);
        }
        $offsets = [];
        $previous = 0;
        for ($index = 0; $index <= $glyphs; ++$index) {
            $offset = $width === 4 ? $table->uint32(20 + 4 * $index) : 2 * $table->uint16(20 + 2 * $index);
            if ($offset < $previous || $offset > $table->size() - $dataAt) {
                throw new MalformedFontException('gvar glyph offsets must increase inside their table.');
            }
            $offsets[] = $offset;
            $previous = $offset;
        }
        $variations = [];
        foreach ($outlines->glyphs as $index => $description) {
            $count = ($description instanceof SimpleGlyph ? count($description->outline->points) : ($description instanceof CompositeGlyph ? count($description->components) : 0)) + 4;
            $size = $offsets[$index + 1] - $offsets[$index];
            $variations[] = $size === 0 ? [] : $tuples->read($table->view($dataAt + $offsets[$index], $size), $axes, $count, $shared, true, $budget);
        }

        return new GvarTable($axes, $variations);
    }
}
