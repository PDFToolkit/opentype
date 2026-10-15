<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$outlines = (new TrueTypeOutlineReader())->read($font);
$engine = new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled);
$output = [];
for ($index = 0; $index < $font->glyphCount(); ++$index) {
    $outline = $engine->outline($outlines, new GlyphId($index));
    $path = (new QuadraticPathBuilder())->build($outline);
    $output[] = ['points' => array_map(fn ($point) => [$point->x, $point->y, $point->onCurve], $outline->points), 'ends' => $outline->endPoints, 'instructions' => bin2hex($outlines->glyph(new GlyphId($index))->instructions()), 'path' => array_map(fn ($command) => [$command->operator->name, array_map(fn ($p) => [$p->x, $p->y], $command->points)], $path->commands)];
}
echo json_encode($output, JSON_THROW_ON_ERROR);
