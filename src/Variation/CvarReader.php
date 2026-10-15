<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

final readonly class CvarReader
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function read(SfntFont $font): CvarTable
    {
        $table = $font->table(new Tag('cvar'));
        if ($font->version !== SfntVersion::TrueType || $table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('cvar requires TrueType and version 1.0.');
        }
        if ($table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('The cvar byte budget was exceeded.');
        }
        $values = (new ControlValueReader())->read($font->table(new Tag('cvt ')));
        $axes = count((new FvarReader())->read($font->table(new Tag('fvar')))->axes);
        $variations = (new TupleVariationReader())->read($table, $axes, count($values->values), [], false, new LayoutReadBudget($this->limits->maxRecords));

        return new CvarTable($axes, count($values->values), $variations);
    }
}
