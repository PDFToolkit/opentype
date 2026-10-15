<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

/** Bounded loca/glyf decoding; no instructions, phantom points or gvar are executed. */
final readonly class TrueTypeOutlineReader
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function read(SfntFont $font): TrueTypeOutlines
    {
        if ($font->version !== SfntVersion::TrueType) {
            throw new UnsupportedFontException('The glyf reader requires a TrueType SFNT.');
        }
        $loca = $font->table(new Tag('loca'));
        $glyf = $font->table(new Tag('glyf'));
        $format = $font->table(new Tag('head'))->int16(50);
        if (!in_array($format, [0, 1], true) || $font->table(new Tag('head'))->int16(52) !== 0 || $loca->size() !== ($font->glyphCount() + 1) * ($format === 0 ? 2 : 4) || $glyf->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('Invalid loca dimensions, glyph format or outline byte budget.');
        }
        $budget = new LayoutReadBudget($this->limits->maxRecords);
        $programBudget = new LayoutReadBudget($this->limits->maxProgramBytes);
        $budget->consume($font->glyphCount());
        $offsets = [];
        $previous = 0;
        for ($index = 0; $index <= $font->glyphCount(); ++$index) {
            $offset = $format === 0 ? $loca->uint16(2 * $index) * 2 : $loca->uint32(4 * $index);
            if ($offset < $previous || $offset > $glyf->size()) {
                throw new MalformedFontException('loca offsets must be monotonic and within glyf.');
            }
            $offsets[] = $offset;
            $previous = $offset;
        }
        $glyphs = [];
        try {
            for ($index = 0; $index < $font->glyphCount(); ++$index) {
                $size = $offsets[$index + 1] - $offsets[$index];
                $glyphs[] = $size === 0 ? new EmptyGlyph() : $this->glyph($glyf->view($offsets[$index], $size), $font->glyphCount(), $budget, $programBudget);
            }

            return new TrueTypeOutlines($glyphs);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed TrueType outline records.', 0, $error);
        }
    }

    private function glyph(BinaryView $data, int $glyphCount, LayoutReadBudget $budget, LayoutReadBudget $programBudget): GlyphDescription
    {
        $contours = $data->int16(0);
        $bounds = new GlyphBounds($data->int16(2), $data->int16(4), $data->int16(6), $data->int16(8));
        $at = 10;
        if ($contours >= 0) {
            $budget->consume($contours);
            $data->view($at, 2 * $contours);
            $endPoints = [];
            $previous = -1;
            for ($index = 0; $index < $contours; ++$index) {
                $end = $data->uint16($at);
                $at += 2;
                if ($end <= $previous) {
                    throw new MalformedFontException('Glyph contour endpoints must increase.');
                }
                $endPoints[] = $end;
                $previous = $end;
            }
            $count = $previous + 1;
            $budget->consume($count);
            $program = $contours === 0 && $at === $data->size() ? '' : $this->program($data, $at, $programBudget);
            $flags = [];
            while (count($flags) < $count) {
                $flag = $data->uint8($at++);
                if (($flag & 0x80) !== 0) {
                    throw new MalformedFontException('A simple glyph uses a reserved flag.');
                }
                $repeat = ($flag & 8) !== 0 ? $data->uint8($at++) + 1 : 1;
                if ($repeat > $count - count($flags)) {
                    throw new MalformedFontException('Repeated glyph flags exceed the point count.');
                }
                for ($index = 0; $index < $repeat; ++$index) {
                    $flags[] = $flag;
                }
            }
            $xs = $this->coordinates($data, $at, $flags, 2, 16);
            $ys = $this->coordinates($data, $at, $flags, 4, 32);
            $points = [];
            foreach ($flags as $index => $flag) {
                $points[] = new OutlinePoint($xs[$index], $ys[$index], ($flag & 1) !== 0);
            }
            $this->padding($data, $at);

            return new SimpleGlyph($bounds, new QuadraticOutline($points, $endPoints), $program, ($flags[0] ?? 0) >> 6 === 1);
        }
        $components = [];
        $hasInstructions = false;
        do {
            $budget->consume(1);
            $flags = $data->uint16($at);
            $glyph = $data->uint16($at + 2);
            $at += 4;
            if ($glyph >= $glyphCount || count(array_filter([($flags & 8) !== 0, ($flags & 64) !== 0, ($flags & 128) !== 0])) > 1) {
                throw new MalformedFontException('Invalid component glyph or mutually exclusive transform flags.');
            }
            $words = ($flags & 1) !== 0;
            $xy = ($flags & 2) !== 0;
            $args = [];
            for ($index = 0; $index < 2; ++$index) {
                $value = $words ? ($xy ? $data->int16($at) : $data->uint16($at)) : $data->uint8($at);
                $args[] = !$words && $xy && $value >= 128 ? $value - 256 : $value;
                $at += $words ? 2 : 1;
            }
            $xx = $yy = 1.0;
            $xyValue = $yx = 0.0;
            if (($flags & 8) !== 0) {
                $xx = $yy = $data->int16($at) / 16384.0;
                $at += 2;
            } elseif (($flags & 64) !== 0) {
                $xx = $data->int16($at) / 16384.0;
                $yy = $data->int16($at + 2) / 16384.0;
                $at += 4;
            } elseif (($flags & 128) !== 0) {
                $xx = $data->int16($at) / 16384.0;
                $xyValue = $data->int16($at + 2) / 16384.0;
                $yx = $data->int16($at + 4) / 16384.0;
                $yy = $data->int16($at + 6) / 16384.0;
                $at += 8;
            }
            $components[] = new GlyphComponent(new GlyphId($glyph), $flags, $args[0], $args[1], new ComponentTransform($xx, $xyValue, $yx, $yy));
            $hasInstructions = $hasInstructions || ($flags & 0x100) !== 0;
        } while (($flags & 0x20) !== 0);
        $program = $hasInstructions ? $this->program($data, $at, $programBudget) : '';
        $this->padding($data, $at);

        return new CompositeGlyph($bounds, $components, $program);
    }

    /**
     * @param list<int> $flags
     * @return list<int>
     */
    private function coordinates(BinaryView $data, int &$at, array $flags, int $short, int $same): array
    {
        $values = [];
        $position = 0;
        foreach ($flags as $flag) {
            if (($flag & $short) !== 0) {
                $position += $data->uint8($at++) * (($flag & $same) !== 0 ? 1 : -1);
            } elseif (($flag & $same) === 0) {
                $position += $data->int16($at);
                $at += 2;
            }
            if ($position < -32768 || $position > 32767) {
                throw new MalformedFontException('A decoded glyph coordinate exceeds signed design units.');
            }
            $values[] = $position;
        }

        return $values;
    }

    private function program(BinaryView $data, int &$at, LayoutReadBudget $budget): string
    {
        $length = $data->uint16($at);
        $at += 2;
        $budget->consume($length);
        $program = $data->bytes($at, $length);
        $at += $length;

        return $program;
    }

    private function padding(BinaryView $data, int $at): void
    {
        if (trim($data->bytes($at, $data->size() - $at), "\0") !== '') {
            throw new MalformedFontException('Glyph padding must contain zero bytes.');
        }
    }
}
