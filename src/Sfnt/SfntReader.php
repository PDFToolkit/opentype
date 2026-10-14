<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Value\Tag;

final readonly class SfntReader
{
    public function __construct(
        private ReadLimits $limits = new ReadLimits(),
        private ChecksumPolicy $checksumPolicy = ChecksumPolicy::All,
    ) {
    }

    public function read(string $bytes): SfntFont
    {
        if (strlen($bytes) > $this->limits->maxBytes) {
            throw new MalformedFontException('The font exceeds the configured byte budget.');
        }
        $font = new BinaryView($bytes);
        $version = SfntVersion::tryFrom($font->uint32(0))
            ?? throw new UnsupportedFontException('Only single SFNT fonts with TrueType or OTTO signatures are supported; TTC and WOFF require separate readers.');
        $count = $font->uint16(4);
        if ($count < 1 || $count > $this->limits->maxTables) {
            throw new MalformedFontException('The font table count exceeds the configured budget or is zero.');
        }
        $directoryEnd = 12 + 16 * $count;
        $font->bytes(0, $directoryEnd);
        // Derive search fields rather than trusting attacker-controlled optimization values.
        $power = 1;
        $selector = 0;
        while ($power * 2 <= $count) {
            $power *= 2;
            ++$selector;
        }
        if ($font->uint16(6) !== $power * 16 || $font->uint16(8) !== $selector
            || $font->uint16(10) !== $count * 16 - $power * 16
        ) {
            throw new MalformedFontException('The SFNT directory search fields do not match its table count.');
        }
        $records = [];
        $previousTag = null;
        foreach (range(0, $count - 1) as $index) {
            $position = 12 + 16 * $index;
            $tagBytes = $font->bytes($position, 4);
            try {
                $tag = new Tag($tagBytes);
            } catch (\InvalidArgumentException $error) {
                throw new MalformedFontException('Invalid SFNT directory tag.', 0, $error);
            }
            if ($previousTag !== null && strcmp($previousTag, $tagBytes) >= 0) {
                throw new MalformedFontException('SFNT directory tags must be sorted and unique.');
            }
            $previousTag = $tagBytes;
            $checksum = $font->uint32($position + 4);
            $offset = $font->uint32($position + 8);
            $length = $font->uint32($position + 12);
            if (($offset & 3) !== 0 || $offset < $directoryEnd) {
                throw new MalformedFontException('A font table must start on a four-byte boundary beyond its directory.');
            }
            $data = $font->view($offset, $length);
            if ($tagBytes === 'head' && $length < 54) {
                throw new MalformedFontException('The font header is truncated.');
            }
            $records[] = new TableRecord($tag, $checksum, $offset, $length, $data);
        }
        $physicalRecords = $records;
        usort($physicalRecords, static fn (TableRecord $a, TableRecord $b): int => $a->offset <=> $b->offset);
        $end = $directoryEnd;
        foreach ($physicalRecords as $record) {
            if ($record->offset < $end) {
                throw new MalformedFontException('Distinct SFNT tables cannot overlap.');
            }
            for ($padding = $end; $padding < $record->offset; $padding += 4096) {
                if (trim($font->bytes($padding, min(4096, $record->offset - $padding)), "\0") !== '') {
                    throw new MalformedFontException('Font table padding must contain zero bytes.');
                }
            }
            $end = $record->offset + $record->length;
        }
        // Validate disjoint byte ranges before doing work proportional to table bytes.
        // This prevents overlapping records from repeatedly hashing the same large payload.
        if ($this->checksumPolicy !== ChecksumPolicy::Ignore) {
            foreach ($records as $record) {
                if ($this->checksum($record->data(), $record->tag->value === 'head' ? 8 : null) !== $record->checksum) {
                    throw new MalformedFontException('Invalid table checksum: '.$record->tag->value);
                }
            }
        }
        $headRecord = null;
        $maxpRecord = null;
        foreach ($records as $record) {
            if ($record->tag->value === 'head') {
                $headRecord = $record;
            } elseif ($record->tag->value === 'maxp') {
                $maxpRecord = $record;
            }
        }
        if ($headRecord === null || $maxpRecord === null) {
            throw new MalformedFontException('An SFNT font requires head and maxp tables.');
        }
        $head = $headRecord->data();
        if ($head->uint32(0) !== 0x00010000 || $head->uint32(12) !== 0x5F0F3CF5) {
            throw new MalformedFontException('Invalid font header version or magic number.');
        }
        if ($head->uint16(18) < 16 || $head->uint16(18) > 16384) {
            throw new MalformedFontException('unitsPerEm must be between 16 and 16384.');
        }
        $maxp = $maxpRecord->data();
        $expectedMaxp = $version === SfntVersion::TrueType ? 0x00010000 : 0x00005000;
        $maxp->bytes(0, $version === SfntVersion::TrueType ? 32 : 6);
        if ($maxp->uint32(0) !== $expectedMaxp) {
            throw new MalformedFontException('The maxp version is incompatible with the SFNT outline kind.');
        }
        $glyphCount = $maxp->uint16(4);
        if ($glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('The glyph count exceeds the configured budget or is zero.');
        }
        if ($this->checksumPolicy === ChecksumPolicy::All && $this->checksum($font) !== 0xB1B0AFBA) {
            throw new MalformedFontException('Invalid whole-font checksum adjustment.');
        }

        return new SfntFont($version, $records, $glyphCount);
    }

    private function checksum(BinaryView $data, ?int $zeroWordOffset = null): int
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
