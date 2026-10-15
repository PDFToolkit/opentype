<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class GlyphMetricVariationsReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, GlyphMetricAxis $axis, int $glyphCount, int $axisCount): GlyphMetricVariations
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only HVAR/VVAR version 1.0 is supported.');
        }
        if ($glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('The metric variation font dimensions exceed their budget.');
        }
        $header = $axis === GlyphMetricAxis::Horizontal ? 20 : 24;
        $table->view(0, $header);
        $reader = new LayoutBinaryReader();
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $store = (new ItemVariationStoreReader())->read($reader->child($table, $table->uint32(4), $header), $budget);
        if ($store->axisCount !== $axisCount) {
            throw new MalformedFontException('The metric variation store and fvar axis counts differ.');
        }
        $mappings = [];
        for ($at = 8; $at < $header; $at += 4) {
            $offset = $table->uint32($at);
            $mappings[] = $offset === 0 ? null : (new DeltaSetIndexMapReader())->read($reader->child($table, $offset, $header), $budget);
        }

        return new GlyphMetricVariations($axis, $glyphCount, $store, $mappings[0], $mappings[1], $mappings[2], $mappings[3] ?? null);
    }
}
