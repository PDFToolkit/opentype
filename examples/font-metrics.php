<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

// File access is application code; all readers below receive bytes or objects.
$limits = new ReadLimits();
$path = $argv[1] ?? dirname(__DIR__).'/tests/fixtures/ToolkitUnicode.ttf';
$bytes = file_get_contents($path, length: max(1, $limits->maxBytes + 1));
if ($bytes === false) {
    throw new RuntimeException('Unable to read the demonstration font.');
}
$font = (new SfntReader($limits))->read($bytes);
$map = (new UnicodeCmapReader($limits))->read($font->table(new Tag('cmap')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);

// U+0041 is a Unicode identity. The resulting glyph ID belongs to this font.
$glyph = $map->glyphFor(new CodePoint(0x41));
$metric = $metrics->metricFor($glyph);

// The fixture supplies an explicit U+0042 + U+FE0F non-default variation.
// Null means the sequence is unsupported; it is distinct from glyph zero.
$variant = $map->glyphForVariation(new CodePoint(0x42), new CodePoint(0xFE0F));
echo json_encode([
    'glyphCount' => $font->glyphCount(),
    'cmapFormat' => $map->format(),
    'glyphForA' => $glyph->value,
    'unitsPerEm' => $metrics->unitsPerEm,
    'advanceWidth' => $metric->advanceWidth,
    'leftSideBearing' => $metric->leftSideBearing,
    'variationGlyph' => $variant?->value,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
