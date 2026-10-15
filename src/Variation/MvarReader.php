<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

final readonly class MvarReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $axisCount): MvarTable
    {
        if ($table->uint32(0) !== 0x00010000) {
            throw new UnsupportedFontException('Only MVAR version 1.0 is supported.');
        }
        $size = $table->uint16(6);
        $count = $table->uint16(8);
        $offset = $table->uint16(10);
        if ($table->size() > $this->limits->maxBytes || $table->uint16(4) !== 0 || $size < 8 || (($count === 0) !== ($offset === 0))) {
            throw new MalformedFontException('Invalid MVAR header, value size or null store.');
        }
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $budget->consume($count);
        $table->view(12, $count * $size);
        $store = $count === 0 ? null : (new ItemVariationStoreReader())->read((new LayoutBinaryReader())->child($table, $offset, 12 + $count * $size), $budget);
        if ($store !== null && $store->axisCount !== $axisCount) {
            throw new MalformedFontException('MVAR and fvar axis counts differ.');
        }
        $records = [];
        try {
            for ($index = 0; $index < $count; ++$index) {
                $at = 12 + $index * $size;
                $records[] = new MetricValueRecord(new Tag($table->bytes($at, 4)), new VariationIndex($table->uint16($at + 4), $table->uint16($at + 6)));
            }

            return new MvarTable($records, $store);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed MVAR value records.', 0, $error);
        }
    }
}
