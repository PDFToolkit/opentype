<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Metadata\FontHeaderReader;
use PDFToolkit\OpenType\Metadata\MaximumProfileReader;
use PDFToolkit\OpenType\Metadata\PostScriptReader;
use PDFToolkit\OpenType\Metadata\GaspReader;
use PDFToolkit\OpenType\Metadata\StyleAttributesReader;
use PDFToolkit\OpenType\Metrics\HorizontalHeaderReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FvarReader;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$output = [
    'head' => (new FontHeaderReader())->read($font->table(new Tag('head'))),
    'hhea' => (new HorizontalHeaderReader())->read($font->table(new Tag('hhea'))),
    'maxp' => (new MaximumProfileReader())->read($font->table(new Tag('maxp'))),
    'post' => (new PostScriptReader())->read($font->table(new Tag('post')), $font->glyphCount()),
];
if ($font->hasTable(new Tag('gasp'))) {
    $output['gasp'] = (new GaspReader())->read($font->table(new Tag('gasp')));
}
if ($font->hasTable(new Tag('STAT'))) {
    $output['STAT'] = (new StyleAttributesReader())->read($font->table(new Tag('STAT')), $font->hasTable(new Tag('fvar')) ? (new FvarReader())->read($font->table(new Tag('fvar'))) : null);
}
echo json_encode($output, JSON_THROW_ON_ERROR);
