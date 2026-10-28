<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

/** TTC/OTC versions 1 and 2 with shared table bytes and explicit face selection. */
final readonly class CollectionReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits(), private ChecksumPolicy $checksumPolicy = ChecksumPolicy::All)
    {
    }

    public function read(string $bytes): SfntCollection
    {
        if (strlen($bytes) > $this->limits->maxBytes) {
            throw new MalformedFontException('The collection exceeds the byte budget.');
        }
        $data = new BinaryView($bytes);
        if ($data->bytes(0, 4) !== 'ttcf' || $data->uint16(6) !== 0 || !in_array($data->uint16(4), [1, 2], true)) {
            throw new UnsupportedFontException('Only TTC/OTC collection headers version 1.0 or 2.0 are supported.');
        }
        $major = $data->uint16(4);
        $count = $data->uint32(8);
        if ($count < 1 || $count > $this->limits->maxLayoutRecords) {
            throw new MalformedFontException('The collection face count exceeds the record budget or is zero.');
        }
        $headerEnd = 12 + 4 * $count + ($major === 2 ? 12 : 0);
        $data->bytes(0, $headerEnd);
        $ranges = [[0, $headerEnd]];
        $remaining = $this->limits->maxLayoutRecords - $count;
        $offsets = [];
        for ($index = 0; $index < $count; ++$index) {
            $offset = $data->uint32(12 + 4 * $index);
            if (($offset & 3) !== 0 || $offset < $headerEnd) {
                throw new MalformedFontException('A collection directory must be aligned and outside its header.');
            }
            $tables = $data->uint16($offset + 4);
            if ($tables > $remaining || $tables > $this->limits->maxTables) {
                throw new MalformedFontException('Collection directories exceed the aggregate record budget.');
            }
            $remaining -= $tables;
            $data->bytes($offset, 12 + 16 * $tables);
            $ranges[] = [$offset, $offset + 12 + 16 * $tables];
            $offsets[] = $offset;
        }
        $signature = null;
        if ($major === 2) {
            $position = 12 + 4 * $count;
            $tag = $data->uint32($position);
            $length = $data->uint32($position + 4);
            $offset = $data->uint32($position + 8);
            if ($tag === 0 && ($length !== 0 || $offset !== 0)) {
                throw new MalformedFontException('An absent collection signature must have zero length and offset.');
            }
            if ($tag !== 0) {
                if ($tag !== 0x44534947 || $length < 8 || ($offset & 3) !== 0 || $offset + $length !== $data->size()) {
                    throw new MalformedFontException('The collection DSIG must occupy the final bounded byte range.');
                }
                $signature = $data->view($offset, $length);
                $ranges[] = [$offset, $offset + $length];
            }
        }
        $fonts = [];
        /** @var array<string, TableRecord> $unique */
        $unique = [];
        $reader = new SfntDirectoryReader($this->limits, ChecksumPolicy::Ignore);
        foreach ($offsets as $offset) {
            $font = $reader->read($data, $offset, true);
            $fonts[] = $font;
            foreach ($font->records() as $record) {
                $key = $record->offset.':'.$record->length;
                if (isset($unique[$key])) {
                    if ($unique[$key]->checksum !== $record->checksum || $unique[$key]->tag->value !== $record->tag->value) {
                        throw new MalformedFontException('A shared collection table must have consistent tag and checksum.');
                    }
                } else {
                    $unique[$key] = $record;
                    $ranges[] = [$record->offset, $record->offset + $record->length];
                }
            }
        }
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $end = 0;
        foreach ($ranges as [$start, $nextEnd]) {
            if ($start < $end) {
                throw new MalformedFontException('Collection headers, directories and distinct table ranges cannot overlap.');
            }
            $end = $nextEnd;
        }
        // Hash each shared payload once; head.checkSumAdjustment is not a collection checksum.
        if ($this->checksumPolicy !== ChecksumPolicy::Ignore) {
            $checksum = new FontChecksum();
            foreach ($unique as $record) {
                if ($checksum->calculate($record->data(), $record->tag->value === 'head' ? 8 : null) !== $record->checksum) {
                    throw new MalformedFontException('Invalid collection table checksum: '.$record->tag->value);
                }
            }
        }

        return new SfntCollection($major, $fonts, $signature);
    }
}
