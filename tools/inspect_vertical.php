<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Metrics\VerticalOriginsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$metrics = (new VerticalMetricsReader())->read($font);
$origins = (new VerticalOriginsReader())->read($font);
$result = [];
for ($index = 0; $index < $font->glyphCount(); ++$index) {
    $glyph = new GlyphId($index);
    $result[] = [$metrics->advanceHeight($glyph), $metrics->topSideBearing($glyph), $origins->originFor($glyph)];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
