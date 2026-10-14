<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

final readonly class EncodingRecord
{
    public function __construct(public int $platformId, public int $encodingId, public int $offset)
    {
        if ($platformId < 0 || $platformId > 65535 || $encodingId < 0 || $encodingId > 65535
            || $offset < 0 || $offset > 0xFFFFFFFF
        ) {
            throw new \InvalidArgumentException('A cmap encoding record requires unsigned 16-bit IDs and a 32-bit offset.');
        }
    }
}
