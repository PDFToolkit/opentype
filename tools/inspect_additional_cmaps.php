<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Tests\AdditionalCmapFixture;
use PDFToolkit\OpenType\Value\CodePoint;

require dirname(__DIR__).'/vendor/autoload.php';

$fixture = new AdditionalCmapFixture();
$cases = [];
foreach ([0, 2, 6, 8, 10, 13] as $format) {
    $bytes = $fixture->cmap($format);
    $map = (new UnicodeCmapReader())->read(new BinaryView($bytes), 5);
    $probes = [];
    foreach ($fixture->probes($format) as $code => $expected) {
        $probes[] = ['codepoint' => $code, 'glyph' => $map->glyphFor(new CodePoint($code))->value];
    }
    $cases[] = ['format' => $map->format(), 'cmap' => base64_encode($bytes), 'probes' => $probes];
}
echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
