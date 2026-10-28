<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;

final readonly class SfntReader
{
    public function __construct(
        private ReadLimits $limits = new ReadLimits(),
        private ChecksumPolicy $checksumPolicy = ChecksumPolicy::All,
    ) {
    }

    public function read(string $bytes): SfntFont
    {
        return (new SfntDirectoryReader($this->limits, $this->checksumPolicy))->read(new BinaryView($bytes));
    }
}
