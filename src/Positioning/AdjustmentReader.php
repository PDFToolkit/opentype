<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** Shared Device and VariationIndex tables used by values, anchors and carets. */
final readonly class AdjustmentReader
{
    public function __construct(private LayoutBinaryReader $reader = new LayoutBinaryReader())
    {
    }

    public function read(BinaryView $parent, int $offset, int $minimum, LayoutReadBudget $budget): DeviceAdjustment|VariationIndex|null
    {
        if ($offset === 0) {
            return null;
        }
        $table = $this->reader->child($parent, $offset, $minimum);
        $start = $table->uint16(0);
        $end = $table->uint16(2);
        $format = $table->uint16(4);
        if ($format === 0x8000) {
            return new VariationIndex($start, $end);
        }
        if ($format < 1 || $format > 3 || $end < $start) {
            throw new MalformedFontException('Invalid GPOS device table.');
        }
        $bits = 1 << $format;
        $count = $end - $start + 1;
        $perWord = intdiv(16, $bits);
        $this->reader->records($table, 6, intdiv($count + $perWord - 1, $perWord), 2, $budget);
        $budget->consume($count);
        $deltas = [];
        for ($i = 0; $i < $count; ++$i) {
            $word = $table->uint16(6 + 2 * intdiv($i, $perWord));
            $value = ($word >> (16 - $bits * (($i % $perWord) + 1))) & ((1 << $bits) - 1);
            $deltas[] = $value >= (1 << ($bits - 1)) ? $value - (1 << $bits) : $value;
        }
        return new DeviceAdjustment($start, $deltas);
    }

}
