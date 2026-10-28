<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Sfnt\CollectionReader;

require dirname(__DIR__).'/vendor/autoload.php';

$path = $argv[1] ?? throw new InvalidArgumentException('A collection path is required.');
$bytes = file_get_contents($path);
if ($bytes === false) {
    throw new RuntimeException('Cannot read collection.');
}
$collection = (new CollectionReader())->read($bytes);
$fonts = [];
for ($index = 0; $index < $collection->count(); ++$index) {
    $font = $collection->font($index);
    $tables = [];
    foreach ($font->records() as $record) {
        $tables[] = ['tag' => $record->tag->value, 'offset' => $record->offset, 'length' => $record->length,
            'checksum' => $record->checksum, 'sha256' => hash('sha256', $record->data()->bytes(0, $record->length))];
    }
    $serialized = (new \PDFToolkit\OpenType\Sfnt\SfntSerializer())->write($font);
    $fonts[] = ['serialized' => base64_encode($serialized), 'version' => $font->version->value, 'glyphCount' => $font->glyphCount(), 'tables' => $tables];
}
echo json_encode(['version' => $collection->majorVersion, 'fonts' => $fonts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
