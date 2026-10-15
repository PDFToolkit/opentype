<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Layout\GdefTable;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\VariationInstance;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

$bytes = file_get_contents($argv[1] ?? '');
if ($bytes === false) {
    throw new RuntimeException('Supply an explicit font fixture path.');
}
$font = (new SfntReader())->read($bytes);
$cmap = (new UnicodeCmapReader())->read($font->table(new Tag('cmap')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$definition = $font->hasTable(new Tag('GDEF')) ? (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount()) : new GdefTable();
$gsub = (new GsubReader())->read($font->table(new Tag('GSUB')), $font->glyphCount());
$gpos = (new GposReader())->read($font->table(new Tag('GPOS')), $font->glyphCount());
$cases = [];
foreach (json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR) as $case) {
    $coordinates = isset($case['coordinates']) ? new NormalizedCoordinates($case['coordinates']) : null;
    $selection = new FeatureSelection(new Tag('latn'), features: array_map(fn (string $tag): Tag => new Tag($tag), $case['features']), coordinates: $coordinates);
    $sequence = new GlyphSequence(array_map(fn (int $code): GlyphId => $cmap->glyphFor(new CodePoint($code)), $case['codePoints']));
    $sequence = (new SubstitutionEngine())->apply($gsub, $selection, $sequence, $definition);
    $positions = array_map(fn (GlyphId $glyph): GlyphPosition => new GlyphPosition($metrics->advanceWidth($glyph)), $sequence->glyphs);
    $context = new PositioningContext($metrics->unitsPerEm, $case['pixelsPerEm'] ?? null, $coordinates !== null && $definition->itemVariationStore !== null ? new VariationInstance($definition->itemVariationStore, $coordinates) : null);
    $positioned = (new PositioningEngine())->apply($gpos, $selection, new PositionedGlyphSequence($sequence, $positions), $definition, $context);
    $x = $y = 0.0;
    $origins = [];
    $values = [];
    foreach ($positioned->positions as $position) {
        $origins[] = [$x + $position->xOffset, $y + $position->yOffset];
        $values[] = [$position->xAdvance, $position->yAdvance, $position->xOffset, $position->yOffset];
        $x += $position->xAdvance;
        $y += $position->yAdvance;
    }
    $cases[] = ['glyphs' => array_map(fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs), 'clusters' => $sequence->clusters, 'positions' => $values, 'origins' => $origins, 'advance' => [$x, $y]];
}
echo json_encode(['cases' => $cases, 'gsubTypes' => array_map(fn ($lookup): int => $lookup->lookupType, $gsub->lookupList->lookups), 'gposTypes' => array_map(fn ($lookup): int => $lookup->lookupType, $gpos->lookups)], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
