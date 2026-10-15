<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\AvarReader;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
$avar = $font->hasTable(new Tag('avar')) ? (new AvarReader())->read($font->table(new Tag('avar')), count($fvar->axes)) : null;
$requests = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$coordinates = [];
foreach ($requests as $request) {
    $coordinates[] = $fvar->normalize(new UserCoordinates($request), $avar)->values;
}
echo json_encode(['axes' => array_map(fn ($axis) => [$axis->tag->value, $axis->minimum, $axis->default, $axis->maximum, $axis->flags, $axis->nameId], $fvar->axes), 'instances' => array_map(fn ($instance) => [$instance->subfamilyNameId, $instance->coordinates, $instance->postScriptNameId], $fvar->instances), 'coordinates' => $coordinates], JSON_THROW_ON_ERROR);
