<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\MetricPhantomProvider;
use PDFToolkit\OpenType\Outline\PathCommand;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\AvarReader;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\GvarInstance;
use PDFToolkit\OpenType\Variation\GvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

require dirname(__DIR__).'/vendor/autoload.php';

$bytes = file_get_contents($argv[1] ?? dirname(__DIR__).'/tests/fixtures/ToolkitGvar.ttf');
if ($bytes === false) {
    throw new RuntimeException('Supply the documented variable TrueType fixture.');
}
$font = (new SfntReader())->read($bytes);
$axes = (new FvarReader())->read($font->table(new Tag('fvar')));
$avar = $font->hasTable(new Tag('avar')) ? (new AvarReader())->read($font->table(new Tag('avar')), count($axes->axes)) : null;
$coordinates = $axes->normalize(new UserCoordinates(['wght' => 700, 'wdth' => 81.25]), $avar);
$outlines = (new TrueTypeOutlineReader())->read($font);
$gvar = (new GvarReader())->read($font, $outlines);
$metrics = new MetricPhantomProvider((new HorizontalMetricsReader())->read($font), (new VerticalMetricsReader())->read($font));
$result = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->glyph($outlines, new GlyphId(2), $metrics, new GvarInstance($gvar, $coordinates));
$phantoms = $result->phantoms ?? throw new RuntimeException('This example supplies explicit phantom metrics.');
echo json_encode([
    'normalizedCoordinates' => $coordinates->values,
    'advanceWidth' => $phantoms->advanceWidth(),
    'advanceHeight' => $phantoms->advanceHeight(),
    'path' => array_map(static fn (PathCommand $command): array => ['operator' => $command->operator->name, 'points' => $command->points], (new QuadraticPathBuilder())->build($result->outline)->commands),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), "\n";
