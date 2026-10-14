<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 2) {
    throw new InvalidArgumentException('Usage: php tools/inspect_font_model.php FONT < requests.json');
}
$fontPath = $argv[1];
$limits = new ReadLimits();
$size = filesize($fontPath);
if ($size === false || $size > $limits->maxBytes) {
    throw new RuntimeException('The input font is missing or exceeds the byte budget.');
}
$bytes = file_get_contents($fontPath);
$requests = stream_get_contents(STDIN);
if ($bytes === false || $requests === false) {
    throw new RuntimeException('Unable to read font comparison inputs.');
}
$input = json_decode($requests, true, flags: JSON_THROW_ON_ERROR);
if (!is_array($input) || !isset($input['codepoints'], $input['variations'])
    || !is_array($input['codepoints']) || !is_array($input['variations'])
) {
    throw new InvalidArgumentException('Oracle requests require codepoint and variation lists.');
}
$font = (new SfntReader($limits))->read($bytes);
$map = (new UnicodeCmapReader($limits))->read($font->table(new Tag('cmap')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$tables = [];
foreach ($font->records() as $record) {
    $tables[] = [
        'tag' => $record->tag->value,
        'checksum' => $record->checksum,
        'length' => $record->length,
        'sha256' => hash('sha256', $record->data()->bytes(0, $record->length)),
    ];
}
$glyphs = [];
foreach ($input['codepoints'] as $point) {
    if (!is_int($point)) {
        throw new InvalidArgumentException('Requested codepoints must be integers.');
    }
    $glyphs[] = ['codepoint' => $point, 'glyph' => $map->glyphFor(new CodePoint($point))->value];
}
$variations = [];
foreach ($input['variations'] as $variation) {
    if (!is_array($variation) || !isset($variation['base'], $variation['selector'])
        || !is_int($variation['base']) || !is_int($variation['selector'])
    ) {
        throw new InvalidArgumentException('Requested variation sequences require integer base and selector values.');
    }
    $variations[] = [
        'base' => $variation['base'],
        'selector' => $variation['selector'],
        'glyph' => $map->glyphForVariation(new CodePoint($variation['base']), new CodePoint($variation['selector']))?->value,
    ];
}
$metricRecords = [];
for ($glyph = 0; $glyph < $font->glyphCount(); ++$glyph) {
    $record = $metrics->metricFor(new GlyphId($glyph));
    $metricRecords[] = [$record->advanceWidth, $record->leftSideBearing];
}
echo json_encode([
    'sfntVersion' => $font->version->value,
    'glyphCount' => $font->glyphCount(),
    'unitsPerEm' => $metrics->unitsPerEm,
    'cmapFormat' => $map->format(),
    'tables' => $tables,
    'glyphs' => $glyphs,
    'variations' => $variations,
    'metrics' => $metricRecords,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
