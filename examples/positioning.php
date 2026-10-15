<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Layout\GdefTable;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

// Application file access is explicit; the readers consume only these bytes.
$bytes = file_get_contents($argv[1] ?? dirname(__DIR__).'/tests/fixtures/ToolkitAdvanced.ttf');
if ($bytes === false) {
    throw new RuntimeException('Supply a readable SFNT font with a GPOS kern feature.');
}
$font = (new SfntReader())->read($bytes);
$map = (new UnicodeCmapReader())->read($font->table(new Tag('cmap')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$definition = $font->hasTable(new Tag('GDEF')) ? (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount()) : new GdefTable();
$gpos = (new GposReader())->read($font->table(new Tag('GPOS')), $font->glyphCount());

// Mapping does not choose features. The application requests exactly kern.
$sequence = new GlyphSequence([$map->glyphFor(new CodePoint(97)), $map->glyphFor(new CodePoint(98))]);
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('kern')]);
$initial = new PositionedGlyphSequence($sequence, array_map(fn (GlyphId $glyph): GlyphPosition => new GlyphPosition($metrics->advanceWidth($glyph)), $sequence->glyphs));
$result = (new PositioningEngine())->apply($gpos, $selection, $initial, $definition);

// The fixture defines a,b kerning of -70: advances become 530 and 600 units.
echo json_encode(['unitsPerEm' => $metrics->unitsPerEm, 'glyphs' => array_map(fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs), 'positions' => $result->positions], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
