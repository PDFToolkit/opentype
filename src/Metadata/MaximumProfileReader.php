<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

final readonly class MaximumProfileReader
{
    public function read(BinaryView $table): MaximumProfile
    {
        $version = $table->uint32(0);
        if (!in_array($version, [0x00005000, 0x00010000], true)) {
            throw new UnsupportedFontException('Only maxp 0.5 and 1.0 are supported.');
        }
        if ($table->size() !== ($version === 0x00005000 ? 6 : 32)) {
            throw new MalformedFontException('maxp size does not match its version.');
        }
        $values = [$version, $table->uint16(4)];
        for ($offset = 6; $offset < $table->size(); $offset += 2) {
            $values[] = $table->uint16($offset);
        }
        try {
            return new MaximumProfile(...$values);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed maxp fields.', 0, $error);
        }
    }
}
