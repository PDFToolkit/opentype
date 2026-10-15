<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Names;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

/** Unicode/Windows Unicode UTF-16BE and ISO ASCII/10646, without extension dependencies. */
final readonly class UnicodeNameDecoder implements NameDecoder
{
    public function decode(NameRecord $record): string
    {
        if ($record->platformId === 2 && $record->encodingId === 0) {
            if (preg_match('/^[\x00-\x7F]*$/D', $record->bytes) !== 1) {
                throw new MalformedFontException('An ASCII name contains a non-ASCII byte.');
            }

            return $record->bytes;
        }
        if ($record->platformId !== 0 && !($record->platformId === 3 && in_array($record->encodingId, [0, 1, 10], true)) && !($record->platformId === 2 && $record->encodingId === 1)) {
            throw new UnsupportedFontException('This name encoding requires an explicit application decoder.');
        }

        return $this->utf16($record->bytes);
    }

    public function utf16(string $bytes): string
    {
        $length = strlen($bytes);
        if (($length & 1) !== 0) {
            throw new MalformedFontException('A UTF-16BE name requires an even byte count.');
        }
        $result = '';
        for ($at = 0; $at < $length; $at += 2) {
            $code = (ord($bytes[$at]) << 8) | ord($bytes[$at + 1]);
            if ($code >= 0xD800 && $code <= 0xDBFF) {
                if ($at + 3 >= $length) {
                    throw new MalformedFontException('Truncated UTF-16 surrogate pair.');
                }
                $low = (ord($bytes[$at + 2]) << 8) | ord($bytes[$at + 3]);
                if ($low < 0xDC00 || $low > 0xDFFF) {
                    throw new MalformedFontException('Invalid UTF-16 low surrogate.');
                }
                $code = 0x10000 + (($code - 0xD800) << 10) + $low - 0xDC00;
                $at += 2;
            } elseif ($code >= 0xDC00 && $code <= 0xDFFF) {
                throw new MalformedFontException('Unpaired UTF-16 low surrogate.');
            }
            $result .= match (true) {
                $code < 0x80 => chr($code),
                $code < 0x800 => chr(0xC0 | ($code >> 6)).chr(0x80 | ($code & 0x3F)),
                $code < 0x10000 => chr(0xE0 | ($code >> 12)).chr(0x80 | (($code >> 6) & 0x3F)).chr(0x80 | ($code & 0x3F)),
                default => chr(0xF0 | ($code >> 18)).chr(0x80 | (($code >> 12) & 0x3F)).chr(0x80 | (($code >> 6) & 0x3F)).chr(0x80 | ($code & 0x3F)),
            };
        }

        return $result;
    }
}
