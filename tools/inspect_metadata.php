<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\Metrics\Os2Reader;
use PDFToolkit\OpenType\Names\NameReader;
use PDFToolkit\OpenType\Names\MacintoshRomanNameDecoder;
use PDFToolkit\OpenType\Names\UnicodeNameDecoder;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$names = (new NameReader())->read($font->table(new Tag('name')));
$output = ['names' => [], 'os2' => []];
foreach ($names->records as $record) {
    $decoder = $record->platformId === 1 ? new MacintoshRomanNameDecoder() : new UnicodeNameDecoder();
    $output['names'][] = [$record->platformId, $record->encodingId, $record->languageId, $record->nameId, base64_encode($record->bytes), $record->text($decoder)];
}
$table = (new Os2Reader())->read($font->table(new Tag('OS/2')));
foreach (get_object_vars($table) as $key => $value) {
    $output['os2'][$key] = $value;
}
echo json_encode($output, JSON_THROW_ON_ERROR);
