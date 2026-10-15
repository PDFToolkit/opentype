<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PDFToolkit\OpenType\DeviceMetrics\HorizontalDeviceMetricsReader;
use PDFToolkit\OpenType\DeviceMetrics\LinearThresholdReader;
use PDFToolkit\OpenType\DeviceMetrics\VerticalDeviceMetricsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;

$font = (new SfntReader())->read(file_get_contents($argv[1]));
$horizontal = (new HorizontalDeviceMetricsReader())->read($font->table(new Tag('hdmx')), $font->glyphCount());
$thresholds = (new LinearThresholdReader())->read($font->table(new Tag('LTSH')), $font->glyphCount());
$vertical = (new VerticalDeviceMetricsReader())->read($font->table(new Tag('VDMX')));
$selected = [];
foreach ([[72,72], [144,72], [96,72], [72,96]] as [$x,$y]) {
    foreach ([8,10,12,24] as $ppem) { $selected[] = $vertical->select($x,$y,1)?->atPpem($ppem); }
}
echo json_encode(['hdmx'=>$horizontal,'LTSH'=>$thresholds,'VDMX'=>$vertical,'selected'=>$selected], JSON_THROW_ON_ERROR);
