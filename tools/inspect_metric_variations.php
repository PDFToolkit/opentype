<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\GlyphMetricAxis;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationInstance;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationsReader;
use PDFToolkit\OpenType\Variation\MvarInstance;
use PDFToolkit\OpenType\Variation\MvarReader;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$horizontal = (new HorizontalMetricsReader())->read($font);
$vertical = (new VerticalMetricsReader())->read($font);
$hvar = (new GlyphMetricVariationsReader())->read($font->table(new Tag('HVAR')), GlyphMetricAxis::Horizontal, $font->glyphCount(), 2);
$vvar = (new GlyphMetricVariationsReader())->read($font->table(new Tag('VVAR')), GlyphMetricAxis::Vertical, $font->glyphCount(), 2);
$mvar = (new MvarReader())->read($font->table(new Tag('MVAR')), 2);
$cases = [];
foreach (json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR) as $values) {
    $coordinates = new NormalizedCoordinates($values);
    $h = new GlyphMetricVariationInstance($hvar, $coordinates);
    $v = new GlyphMetricVariationInstance($vvar, $coordinates);
    $m = new MvarInstance($mvar, $coordinates);
    $case = ['glyphs' => [], 'mvar' => []];
    for ($index = 0; $index < $font->glyphCount(); ++$index) {
        $glyph = new GlyphId($index);
        $case['glyphs'][] = [$horizontal->advanceWidth($glyph) + $h->advanceDelta($glyph), $vertical->advanceHeight($glyph) + $v->advanceDelta($glyph), $h->firstBearingDelta($glyph), $h->secondBearingDelta($glyph), $v->firstBearingDelta($glyph), $v->secondBearingDelta($glyph), $v->verticalOriginDelta($glyph)];
    }
    foreach ($mvar->records as $record) {
        $case['mvar'][$record->tag->value] = $m->delta($record->tag);
    }
    $cases[] = $case;
}
echo json_encode(['header' => $vertical->header, 'verticalMetrics' => array_map(fn ($i) => $vertical->metricFor(new GlyphId($i)), range(0, $font->glyphCount() - 1)), 'cases' => $cases], JSON_THROW_ON_ERROR);
