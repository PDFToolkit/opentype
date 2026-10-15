<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\Tag;

final readonly class FvarReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table): FvarTable
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only fvar version 1.0 is supported.');
        }
        $offset = $table->uint16(4);
        $axisCount = $table->uint16(8);
        $axisSize = $table->uint16(10);
        $instanceCount = $table->uint16(12);
        $instanceSize = $table->uint16(14);
        if ($table->size() > $this->limits->maxBytes || $offset < 16 || $table->uint16(6) !== 2 || $axisSize !== 20
            || !in_array($instanceSize, [4 + 4 * $axisCount, 6 + 4 * $axisCount], true)
            || $axisCount + $instanceCount * (1 + $axisCount) > $this->limits->maxLayoutRecords
        ) {
            throw new MalformedFontException('Invalid fvar header or aggregate record budget.');
        }
        $table->view($offset, $axisCount * $axisSize + $instanceCount * $instanceSize);
        try {
            $axes = [];
            for ($index = 0; $index < $axisCount; ++$index) {
                $at = $offset + $index * $axisSize;
                $axes[] = new VariationAxis(new Tag($table->bytes($at, 4)), $this->fixed($table, $at + 4), $this->fixed($table, $at + 8), $this->fixed($table, $at + 12), $table->uint16($at + 16), $table->uint16($at + 18));
            }
            $instances = [];
            for ($index = 0; $index < $instanceCount; ++$index) {
                $at = $offset + $axisCount * $axisSize + $index * $instanceSize;
                $values = [];
                for ($axis = 0; $axis < $axisCount; ++$axis) {
                    $values[] = $this->fixed($table, $at + 4 + 4 * $axis);
                }
                $instances[] = new NamedInstance($table->uint16($at), $table->uint16($at + 2), $values, $instanceSize === 6 + 4 * $axisCount ? $table->uint16($at + 4 + 4 * $axisCount) : null);
            }

            return new FvarTable($axes, $instances);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed fvar records.', 0, $error);
        }
    }

    private function fixed(BinaryView $table, int $offset): float
    {
        $value = $table->uint32($offset);

        return ($value >= 0x80000000 ? $value - 0x100000000 : $value) / 65536.0;
    }
}
