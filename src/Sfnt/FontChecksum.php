<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;

/** OpenType unsigned word checksum; head.checkSumAdjustment can be excluded. */
final readonly class FontChecksum
{
    public function calculate(BinaryView $data, ?int $zeroWordOffset = null): int
    {
        $checksum = 0;
        $size = $data->size();
        for ($offset = 0; $offset < $size; $offset += 4) {
            if ($offset === $zeroWordOffset) {
                continue;
            }
            if ($size - $offset >= 4) {
                $word = $data->uint32($offset);
            } else {
                $word = 0;
                for ($byte = 0; $byte < $size - $offset; ++$byte) {
                    $word |= $data->uint8($offset + $byte) << (24 - 8 * $byte);
                }
            }
            $checksum = ($checksum + $word) & 0xFFFFFFFF;
        }

        return $checksum;
    }
}
