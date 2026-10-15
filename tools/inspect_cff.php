<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Cff\CffReader;
use PDFToolkit\OpenType\Cff\CffOutlineEngine;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$cff = (new CffReader())->read($font);
$engine = new CffOutlineEngine();
$coordinates = isset($argv[2]) ? new NormalizedCoordinates(json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR)) : null;
$output = [];
for ($index = 0; $index < $font->glyphCount(); ++$index) {
    $outline = $engine->outline($cff, new GlyphId($index), $coordinates);
    $output[] = ['width' => $outline->width, 'stems' => $outline->stemCount, 'path' => array_map(fn ($command) => [$command->operator->name, array_map(fn ($p) => [$p->x, $p->y], $command->points)], $outline->path->commands)];
}
echo json_encode($output, JSON_THROW_ON_ERROR);
