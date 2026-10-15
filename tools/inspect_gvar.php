<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\MetricPhantomProvider;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\ControlValueReader;
use PDFToolkit\OpenType\Variation\CvarReader;
use PDFToolkit\OpenType\Variation\GvarReader;
use PDFToolkit\OpenType\Variation\GvarInstance;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$outlines = (new TrueTypeOutlineReader())->read($font);
$coordinates = new NormalizedCoordinates(json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR));
$instance = new GvarInstance((new GvarReader())->read($font, $outlines), $coordinates);
$phantoms = new MetricPhantomProvider((new HorizontalMetricsReader())->read($font), (new VerticalMetricsReader())->read($font));
$engine = new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled);
$output = [];
for ($index = 0; $index < $font->glyphCount(); ++$index) {
    $glyph = $engine->glyph($outlines, new GlyphId($index), $phantoms, $instance);
    $path = (new QuadraticPathBuilder())->build($glyph->outline);
    $output[] = ['points' => array_map(fn ($point) => [$point->x, $point->y], $glyph->outline->points), 'ends' => $glyph->outline->endPoints, 'phantoms' => array_map(fn ($point) => [$point->x, $point->y], $glyph->phantoms->points()), 'path' => array_map(fn ($command) => [$command->operator->name, array_map(fn ($p) => [$p->x, $p->y], $command->points)], $path->commands)];
}
$cvt = (new ControlValueReader())->read($font->table(new Tag('cvt ')));
echo json_encode(['glyphs' => $output, 'cvt' => (new CvarReader())->read($font)->instance($cvt, $coordinates)->values], JSON_THROW_ON_ERROR);
