<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class CffNumberReader
{
    /** Consume one encoded number; callers have already identified its first byte. */
    public function read(BinaryView $data, int &$at, bool $dictionary): float
    {
        $byte = $data->uint8($at++);
        if ($byte >= 32 && $byte <= 246) {
            return (float) ($byte - 139);
        }
        if ($byte >= 247 && $byte <= 250) {
            return (float) (($byte - 247) * 256 + $data->uint8($at++) + 108);
        }
        if ($byte >= 251 && $byte <= 254) {
            return (float) (-($byte - 251) * 256 - $data->uint8($at++) - 108);
        }
        if ($byte === 28) {
            $value = $data->int16($at);
            $at += 2;

            return (float) $value;
        }
        if (($byte === 29 && $dictionary) || ($byte === 255 && !$dictionary)) {
            $value = $data->uint32($at);
            $at += 4;
            $value = $value >= 0x80000000 ? $value - 0x100000000 : $value;

            return $byte === 255 ? $value / 65536.0 : (float) $value;
        }
        if ($byte === 30 && $dictionary) {
            $text = '';
            while (strlen($text) < 128) {
                $pair = $data->uint8($at++);
                foreach ([$pair >> 4, $pair & 15] as $position => $nibble) {
                    if ($nibble === 15) {
                        if (($position === 0 && ($pair & 15) !== 15) || preg_match('/^-?(?:[1-9][0-9]*|0)?(?:\.[0-9]*)?(?:E-?[1-9][0-9]*)?$/D', $text) !== 1 || str_starts_with(ltrim($text, '-'), 'E') || !is_finite((float) $text)) {
                            throw new MalformedFontException('Invalid CFF real-number encoding.');
                        }

                        return (float) $text;
                    }
                    $text .= match ($nibble) {
                        10 => '.', 11 => 'E', 12 => 'E-', 14 => '-',
                        13 => throw new MalformedFontException('Reserved CFF real-number nibble.'),
                        default => (string) $nibble,
                    };
                }
            }
            throw new MalformedFontException('A CFF decimal exceeds its bounded representation.');
        }
        throw new MalformedFontException('Invalid CFF numeric encoding for this context.');
    }
}
