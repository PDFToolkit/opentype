<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class DeltaSetIndexMapReader
{
    public function read(BinaryView $table, LayoutReadBudget $budget): DeltaSetIndexMap
    {
        $format = $table->uint8(0);
        if (!in_array($format, [0, 1], true)) {
            throw new UnsupportedFontException('Unknown delta-set index map format.');
        }
        $entryFormat = $table->uint8(1);
        $width = (($entryFormat >> 4) & 3) + 1;
        $bits = ($entryFormat & 15) + 1;
        $count = $format === 0 ? $table->uint16(2) : $table->uint32(2);
        $at = $format === 0 ? 4 : 6;
        if (($entryFormat & 0xC0) !== 0 || $count < 1 || $bits > 8 * $width) {
            throw new MalformedFontException('Invalid delta-set map flags, width or count.');
        }
        $budget->consume($count);
        $table->view($at, $count * $width);
        $entries = [];
        for ($index = 0; $index < $count; ++$index) {
            $value = 0;
            for ($byte = 0; $byte < $width; ++$byte) {
                $value = ($value << 8) | $table->uint8($at++);
            }
            $outer = $value >> $bits;
            if ($outer > 65535) {
                throw new MalformedFontException('A packed outer variation index exceeds uint16.');
            }
            $entries[] = new VariationIndex($outer, $value & ((1 << $bits) - 1));
        }

        return new DeltaSetIndexMap($format, $entries);
    }
}
