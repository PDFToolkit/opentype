<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\AlternateIndex;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

// This original test font has intentionally small and easily inspected GSUB rules.
// Supply another font path as the first argument to inspect its own supported features.
$limits = new ReadLimits();
$path = $argv[1] ?? dirname(__DIR__).'/tests/fixtures/ToolkitLayout.ttf';
$bytes = file_get_contents($path, length: max(1, $limits->maxBytes + 1));
if ($bytes === false) {
    throw new RuntimeException('Unable to read the font.');
}
$font = (new SfntReader($limits))->read($bytes);
$cmap = (new UnicodeCmapReader($limits))->read($font->table(new Tag('cmap')), $font->glyphCount());
$gsub = (new GsubReader($limits))->read($font->table(new Tag('GSUB')), $font->glyphCount());
$engine = new SubstitutionEngine($limits);

// cmap selects the initial glyphs. It does not compose "ffi" into a ligature.
$input = new GlyphSequence([
    $cmap->glyphFor(new CodePoint(0x66)), // f
    $cmap->glyphFor(new CodePoint(0x66)), // f
    $cmap->glyphFor(new CodePoint(0x69)), // i
]);
$ligature = $engine->apply($gsub, new FeatureSelection(new Tag('latn'), features: [new Tag('liga')]), $input);

// Feature selection is explicit. Alternate selection is also an explicit object.
$letter = new GlyphSequence([$cmap->glyphFor(new CodePoint(0x61))]);
$smallCapital = $engine->apply($gsub, new FeatureSelection(new Tag('latn'), features: [new Tag('smcp')]), $letter);
$alternate = $engine->apply($gsub, new FeatureSelection(new Tag('latn'), features: [new Tag('salt')], alternateSelector: new AlternateIndex(2)), $letter);

// A Multiple lookup can decompose the fi glyph back into f and i.
$composed = new GlyphSequence([$cmap->glyphFor(new CodePoint(0xFB01))]);
$decomposed = $engine->apply($gsub, new FeatureSelection(new Tag('latn'), features: [new Tag('ccmp')]), $composed);
$ids = static fn (GlyphSequence $sequence): array => array_map(static fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs);

echo json_encode([
    'inputGlyphs' => $ids($input),
    'ligatureGlyphs' => $ids($ligature),
    'ligatureClusters' => $ligature->clusters,
    'smallCapitalGlyphs' => $ids($smallCapital),
    'alternateGlyphs' => $ids($alternate),
    'multipleSubstitutionGlyphs' => $ids($decomposed),
    'multipleSubstitutionClusters' => $decomposed->clusters,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
