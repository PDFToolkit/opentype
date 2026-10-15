<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\VariationInstance;

require dirname(__DIR__).'/vendor/autoload.php';

$bytes = file_get_contents($argv[1] ?? dirname(__DIR__).'/tests/fixtures/ToolkitVariable.ttf');
if ($bytes === false) {
    throw new RuntimeException('Supply the documented variable layout fixture.');
}
$font = (new SfntReader())->read($bytes);
$map = (new UnicodeCmapReader())->read($font->table(new Tag('cmap')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$definition = (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount());
$store = $definition->itemVariationStore ?? throw new RuntimeException('This example requires GDEF variation data.');

// This fixture has one wght axis. Normalization and axis order are explicit.
$coordinates = new NormalizedCoordinates([0.5]);
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('sng2')], coordinates: $coordinates);
$context = new PositioningContext($metrics->unitsPerEm, variations: new VariationInstance($store, $coordinates));
$glyph = $map->glyphFor(new CodePoint(97));
$input = new PositionedGlyphSequence(new GlyphSequence([$glyph]), [new GlyphPosition($metrics->advanceWidth($glyph))]);
$gpos = (new GposReader())->read($font->table(new Tag('GPOS')), $font->glyphCount());
$result = (new PositioningEngine())->apply($gpos, $selection, $input, $definition, $context);

// 600 initial + 10 ordinary adjustment + 100 × 0.5 variable delta = 660 units.
echo json_encode(['normalizedCoordinates' => $coordinates->values, 'positions' => $result->positions], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
