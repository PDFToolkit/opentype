<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;

/** Rebuild one selected face without subsetting; discard invalidated DSIG signatures. */
final readonly class SfntSerializer
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function write(SfntFont $font): string
    {
        $records = array_values(array_filter($font->records(), static fn (TableRecord $record): bool => $record->tag->value !== 'DSIG'));
        usort($records, static fn (TableRecord $a, TableRecord $b): int => strcmp($a->tag->value, $b->tag->value));
        $count = count($records);
        if ($count < 1 || $count > $this->limits->maxTables) {
            throw new \InvalidArgumentException('Serialized table count exceeds its budget.');
        }
        $power = 1;
        $selector = 0;
        while ($power * 2 <= $count) {
            $power *= 2;
            ++$selector;
        }
        $header = pack('Nnnnn', $font->version->value, $count, 16 * $power, $selector, 16 * ($count - $power));
        $offset = 12 + 16 * $count;
        $body = '';
        $headOffset = null;
        $checksum = new FontChecksum();
        foreach ($records as $record) {
            $length = $record->length;
            $padded = ($length + 3) & ~3;
            if ($padded > $this->limits->maxBytes - $offset) {
                throw new \InvalidArgumentException('Serialized font exceeds its byte budget.');
            }
            $bytes = $record->data()->bytes(0, $length);
            if ($record->tag->value === 'head') {
                if ($length < 54) {
                    throw new \InvalidArgumentException('The serialized font header is truncated.');
                }
                $bytes = substr_replace($bytes, "\0\0\0\0", 8, 4);
                $headOffset = $offset;
            }
            $header .= $record->tag->value.pack('NNN', $checksum->calculate(new BinaryView($bytes)), $offset, $length);
            $body .= str_pad($bytes, $padded, "\0");
            $offset += $padded;
        }
        if ($headOffset === null) {
            throw new \InvalidArgumentException('Serialization requires a head table.');
        }
        $bytes = $header.$body;
        $bytes = substr_replace($bytes, pack('N', (0xB1B0AFBA - $checksum->calculate(new BinaryView($bytes))) & 0xFFFFFFFF), $headOffset + 8, 4);
        (new SfntReader($this->limits))->read($bytes);

        return $bytes;
    }
}
