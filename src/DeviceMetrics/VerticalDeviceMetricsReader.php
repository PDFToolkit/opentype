<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class VerticalDeviceMetricsReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }
    public function read(BinaryView $table): VerticalDeviceMetrics
    {
        $version = $table->uint16(0);
        $groupCount = $table->uint16(2);
        $ratioCount = $table->uint16(4);
        if ($version > 1) {
            throw new UnsupportedFontException('Only VDMX versions 0/1 are supported.');
        }
        if ($groupCount < 1 || $ratioCount < 1 || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('Invalid VDMX size or counts.');
        }
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $budget->consume($ratioCount + $groupCount);
        $dataStart = 6 + 6 * $ratioCount;
        $table->view(6, $dataStart - 6);
        $groups = [];
        $ratios = [];
        try {
            for ($index = 0; $index < $ratioCount; ++$index) {
                $offset = $table->uint16(6 + 4 * $ratioCount + 2 * $index);
                if ($offset < $dataStart) {
                    throw new MalformedFontException('VDMX group overlaps its directory.');
                }
                if (!isset($groups[$offset])) {
                    $count = $table->uint16($offset);
                    $budget->consume($count);
                    $table->view($offset, 4 + 6 * $count);
                    $heights = [];
                    for ($height = 0; $height < $count; ++$height) {
                        $record = $offset + 4 + 6 * $height;
                        $heights[] = new VerticalDeviceHeight($table->uint16($record), $table->int16($record + 2), $table->int16($record + 4));
                    }
                    $groups[$offset] = new VerticalDeviceGroup($table->uint8($offset + 2), $table->uint8($offset + 3), $heights);
                }
                $ratio = 6 + 4 * $index;
                $ratios[] = new DeviceRatio($table->uint8($ratio), $table->uint8($ratio + 1), $table->uint8($ratio + 2), $table->uint8($ratio + 3), $groups[$offset]);
            }
            if (count($groups) !== $groupCount) {
                throw new MalformedFontException('VDMX distinct group count differs from its header.');
            }
            return new VerticalDeviceMetrics($version, $ratios);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Invalid VDMX records.', 0, $error);
        }
    }
}
