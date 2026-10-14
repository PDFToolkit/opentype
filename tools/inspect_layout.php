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

$limits = new ReadLimits();
$bytes = file_get_contents($argv[1] ?? '', length: $limits->maxBytes + 1);
if ($bytes === false) {
    throw new RuntimeException('The oracle requires a readable font path.');
}
$font = (new SfntReader($limits))->read($bytes);
$cmap = (new UnicodeCmapReader($limits))->read($font->table(new Tag('cmap')), $font->glyphCount());
$gsub = (new GsubReader($limits))->read($font->table(new Tag('GSUB')), $font->glyphCount());
$engine = new SubstitutionEngine($limits);
$requests = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$cases = [];
foreach ($requests as $case) {
    $input = new GlyphSequence(array_map(
        static fn (int $codePoint): GlyphId => $cmap->glyphFor(new CodePoint($codePoint)),
        $case['codePoints'],
    ));
    $selection = new FeatureSelection(
        new Tag($case['script']),
        isset($case['language']) ? new Tag($case['language']) : null,
        array_map(static fn (string $tag): Tag => new Tag($tag), $case['features']),
        isset($case['alternate']) ? new AlternateIndex($case['alternate']) : null,
    );
    $output = $engine->apply($gsub, $selection, $input);
    $cases[] = [
        'glyphs' => array_map(static fn (GlyphId $glyph): int => $glyph->value, $output->glyphs),
        'clusters' => $output->clusters,
    ];
}
echo json_encode([
    'glyphCount' => $font->glyphCount(),
    'tables' => array_map(static fn ($record): string => $record->tag->value, $font->records()),
    'scripts' => array_map(static fn ($record): string => $record->scriptTag->value, $gsub->scriptList->scriptRecords),
    'features' => array_map(static fn ($record): string => $record->featureTag->value, $gsub->featureList->featureRecords),
    'lookupTypes' => array_map(static fn ($lookup): int => $lookup->lookupType, $gsub->lookupList->lookups),
    'cases' => $cases,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
