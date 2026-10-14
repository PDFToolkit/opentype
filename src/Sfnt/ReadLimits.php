<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

/** Explicit resource budgets, applied before allocating arrays or following offsets. */
final readonly class ReadLimits
{
    public function __construct(
        public int $maxBytes = 67108864,
        public int $maxTables = 128,
        public int $maxGlyphs = 65535,
        public int $maxLayoutRecords = 16384,
        public int $maxOutputGlyphs = 262144,
        public int $maxSubstitutionOperations = 1048576,
    ) {
        if ($maxBytes < 1 || $maxBytes > 1073741824
            || $maxTables < 1 || $maxTables > 4095
            || $maxGlyphs < 1 || $maxGlyphs > 65535
            || $maxLayoutRecords < 1 || $maxLayoutRecords > 1048576
            || $maxOutputGlyphs < 1 || $maxOutputGlyphs > 1048576
            || $maxSubstitutionOperations < 1 || $maxSubstitutionOperations > 1073741824
        ) {
            throw new \InvalidArgumentException('OpenType read limits must be positive and within their bounded supported ranges.');
        }
    }
}
