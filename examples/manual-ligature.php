<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\LookupList;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubTable;
use PDFToolkit\OpenType\Substitution\Ligature;
use PDFToolkit\OpenType\Substitution\LigatureSet;
use PDFToolkit\OpenType\Substitution\LigatureSubstFormat1;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

// Build the same structures a GSUB reader would produce, without reading bytes.
// These are illustrative font-local identities: f=4, i=5 and ffi=7.
$f = new GlyphId(4);
$i = new GlyphId(5);
$ffi = new GlyphId(7);

// Coverage supplies the first component; Ligature lists the remaining f and i.
$subtable = new LigatureSubstFormat1(
    new CoverageFormat1([$f]),
    [new LigatureSet([new Ligature($ffi, [$f, $i])])],
);
$lookups = new LookupList([new Lookup(4, new LookupFlags(0), [$subtable])]);

// Feature index 0 selects lookup index 0. The Latin default language permits it.
$features = new FeatureList([new FeatureRecord(new Tag('liga'), new Feature([0]))]);
$scripts = new ScriptList([
    new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0]))),
]);
$gsub = new GsubTable($scripts, $features, $lookups, glyphCount: 8);
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('liga')]);
$result = (new SubstitutionEngine())->apply($gsub, $selection, new GlyphSequence([$f, $f, $i]));

echo json_encode([
    'glyphs' => array_map(static fn (GlyphId $glyph): int => $glyph->value, $result->glyphs),
    'clusters' => $result->clusters,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
