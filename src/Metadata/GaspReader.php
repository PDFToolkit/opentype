<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class GaspReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table): GaspTable
    {
        $version = $table->uint16(0);
        $count = $table->uint16(2);
        if ($version > 1) {
            throw new UnsupportedFontException('Only gasp versions 0/1 are supported.');
        }
        if ($count < 1 || $count > $this->limits->maxLayoutRecords || $table->size() !== 4 + 4 * $count || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('Invalid gasp size or range budget.');
        }
        try {
            $ranges = [];
            for ($index = 0; $index < $count; ++$index) {
                $ranges[] = new GaspRange($table->uint16(4 + 4 * $index), $table->uint16(6 + 4 * $index));
            }

            return new GaspTable($version, $ranges);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed gasp ranges.', 0, $error);
        }
    }
}
