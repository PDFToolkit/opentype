<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;

final readonly class PostScriptReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $glyphCount): PostScriptTable
    {
        $version = $table->uint32(0);
        if (!in_array($version, [0x10000, 0x20000, 0x25000, 0x30000], true)) {
            throw new UnsupportedFontException('Only OpenType post versions 1/2/2.5/3 are supported.');
        }
        if ($table->size() > $this->limits->maxBytes || $glyphCount < 1 || $glyphCount > 65535 || $glyphCount > $this->limits->maxLayoutRecords) {
            throw new MalformedFontException('post dimensions or byte budget exceeded.');
        }
        if (($version === 0x10000 || $version === 0x30000) && $table->size() !== 32) {
            throw new MalformedFontException('post 1.0/3.0 consist only of their 32-byte header.');
        }
        $names = null;
        if ($version === 0x10000) {
            if ($glyphCount !== 258) {
                throw new MalformedFontException('post 1.0 requires exactly 258 standard glyphs.');
            }
            $names = array_slice(PostStandardNames::NAMES, 0, $glyphCount);
        } elseif ($version === 0x20000 || $version === 0x25000) {
            $count = $table->uint16(32);
            if ($count !== $glyphCount) {
                throw new MalformedFontException('post and maxp glyph counts differ.');
            }
            $indices = [];
            $maximum = 257;
            for ($index = 0; $index < $count; ++$index) {
                if ($version === 0x25000) {
                    $delta = $table->uint8(34 + $index);
                    $value = $index + ($delta >= 128 ? $delta - 256 : $delta);
                } else {
                    $value = $table->uint16(34 + 2 * $index);
                }
                if ($value < 0 || $value > ($version === 0x25000 ? 257 : 65535)) {
                    throw new MalformedFontException('A post glyph-name index exceeds its format.');
                }
                $indices[] = $value;
                $maximum = max($maximum, $value);
            }
            if ($count + $maximum - 257 > $this->limits->maxLayoutRecords) {
                throw new MalformedFontException('The aggregate post name budget was exceeded.');
            }
            $at = 34 + $count * ($version === 0x25000 ? 1 : 2);
            $custom = [];
            for ($index = 258; $index <= $maximum; ++$index) {
                $length = $table->uint8($at++);
                $custom[$index] = $table->bytes($at, $length);
                $at += $length;
                if ($length < 1 || $length > 63 || preg_match('/^[A-Za-z0-9._]+$/D', $custom[$index]) !== 1) {
                    throw new MalformedFontException('A post glyph name must contain 1–63 permitted ASCII characters.');
                }
            }
            $names = [];
            foreach ($indices as $index) {
                $names[] = $index < 258 ? PostStandardNames::NAMES[$index] : $custom[$index];
            }
        }
        $angle = $table->uint32(4);
        $angle = ($angle >= 0x80000000 ? $angle - 0x100000000 : $angle) / 65536.0;

        return new PostScriptTable($version, $angle, $table->int16(8), $table->int16(10), $table->uint32(12), $table->uint32(16), $table->uint32(20), $table->uint32(24), $table->uint32(28), $names);
    }
}
