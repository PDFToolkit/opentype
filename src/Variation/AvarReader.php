<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class AvarReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $axisCount): AvarTable
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only avar version 1.0 is supported.');
        }
        if ($axisCount < 0 || $axisCount > $this->limits->maxLayoutRecords || $table->size() > $this->limits->maxBytes || $table->uint16(4) !== 0 || $table->uint16(6) !== $axisCount) {
            throw new MalformedFontException('Invalid avar header, axis count or record budget.');
        }
        $table->view(8, 2 * $axisCount);
        $at = 8;
        $remaining = $this->limits->maxLayoutRecords - $axisCount;
        $segments = [];
        try {
            for ($axis = 0; $axis < $axisCount; ++$axis) {
                $count = $table->uint16($at);
                $at += 2;
                if ($count > $remaining) {
                    throw new MalformedFontException('The avar aggregate mapping budget was exceeded.');
                }
                $remaining -= $count;
                $table->view($at, 4 * $count);
                $mappings = [];
                for ($index = 0; $index < $count; ++$index) {
                    $mappings[] = new AxisValueMap($table->int16($at), $table->int16($at + 2));
                    $at += 4;
                }
                $segments[] = new AxisSegmentMap($mappings);
            }

            return new AvarTable($segments);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed avar segment map.', 0, $error);
        }
    }
}
