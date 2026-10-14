<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Value\Tag;

final readonly class TableRecord
{
    public function __construct(
        public Tag $tag,
        public int $checksum,
        public int $offset,
        public int $length,
        private BinaryView $data,
    ) {
        if ($checksum < 0 || $checksum > 0xFFFFFFFF || $offset < 0 || $offset > 0xFFFFFFFF
            || $length < 0 || $length > 0xFFFFFFFF || $data->size() !== $length
        ) {
            throw new \InvalidArgumentException('A table record must contain bounded SFNT fields and exactly its declared bytes.');
        }
    }

    public function data(): BinaryView
    {
        return $this->data;
    }
}
