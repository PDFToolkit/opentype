<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Names;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class NameReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table): NameTable
    {
        $version = $table->uint16(0);
        if (!in_array($version, [0, 1], true)) {
            throw new UnsupportedFontException('Only name table versions 0 and 1 are supported.');
        }
        $count = $table->uint16(2);
        $storageOffset = $table->uint16(4);
        if ($table->size() > $this->limits->maxBytes || $count > $this->limits->maxLayoutRecords) {
            throw new MalformedFontException('The name byte or record budget was exceeded.');
        }
        $table->view(6, 12 * $count);
        $end = 6 + 12 * $count;
        $tagCount = $version === 1 ? $table->uint16($end) : 0;
        $tagStart = $end + 2;
        if ($tagCount > min(32768, $this->limits->maxLayoutRecords - $count)) {
            throw new MalformedFontException('The language-tag record budget was exceeded.');
        }
        if ($version === 1) {
            $table->view($tagStart, 4 * $tagCount);
            $end = $tagStart + 4 * $tagCount;
        }
        if ($storageOffset < $end) {
            throw new MalformedFontException('Name string storage cannot overlap its record arrays.');
        }
        $storage = $table->view($storageOffset);
        $remainingBytes = $this->limits->maxBytes;
        $tags = [];
        $decoder = new UnicodeNameDecoder();
        for ($index = 0; $index < $tagCount; ++$index) {
            $length = $table->uint16($tagStart + 4 * $index);
            $this->consume($length, $remainingBytes);
            $tags[] = $decoder->utf16($storage->bytes($table->uint16($tagStart + 4 * $index + 2), $length));
        }
        $records = [];
        $previous = null;
        for ($index = 0; $index < $count; ++$index) {
            $at = 6 + 12 * $index;
            $key = [$table->uint16($at), $table->uint16($at + 2), $table->uint16($at + 4), $table->uint16($at + 6)];
            if ($previous !== null && $key < $previous) {
                throw new MalformedFontException('Name records must be sorted by platform, encoding, language and name identifiers.');
            }
            if ($version === 0 && $key[2] >= 0x8000 && ($key[0] < 240 || $key[0] > 255)) {
                throw new MalformedFontException('Version 0 has no language-tag records.');
            }
            $previous = $key;
            $length = $table->uint16($at + 8);
            $this->consume($length, $remainingBytes);
            $tag = $version === 1 && $key[2] >= 0x8000 ? ($tags[$key[2] - 0x8000] ?? null) : null;
            $records[] = new NameRecord($key[0], $key[1], $key[2], $key[3], $storage->bytes($table->uint16($at + 10), $length), $tag, !($version === 1 && $key[2] >= 0x8000 && $tag === null));
        }

        return new NameTable($version, $records, $tags);
    }

    private function consume(int $length, int &$remaining): void
    {
        if ($length > $remaining) {
            throw new MalformedFontException('The aggregate name string-byte budget was exceeded.');
        }
        $remaining -= $length;
    }
}
