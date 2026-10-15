<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Value\Tag;

/** Default vhea/vmtx metrics; VVAR adjustments and outline origins are explicit operations. */
final readonly class VerticalMetricsReader
{
    public function read(SfntFont $font): VerticalMetrics
    {
        $table = $font->table(new Tag('vhea'));
        $metricsTable = $font->table(new Tag('vmtx'));
        if (!in_array($table->uint32(0), [0x00010000, 0x00011000], true)) {
            throw new UnsupportedFontException('Only vhea 1.0 and 1.1 are supported.');
        }
        if ($table->size() !== 36 || $table->bytes(24, 10) !== str_repeat("\0", 10)) {
            throw new MalformedFontException('The vhea reserved fields, metric format or length are invalid.');
        }
        $count = $table->uint16(34);
        if ($count < 1 || $count > $font->glyphCount() || $metricsTable->size() !== 4 * $count + 2 * ($font->glyphCount() - $count)) {
            throw new MalformedFontException('The vmtx dimensions do not match vhea and maxp.');
        }
        try {
            $header = new VerticalHeader($table->uint32(0), $table->int16(4), $table->int16(6), $table->int16(8), $table->uint16(10), $table->int16(12), $table->int16(14), $table->int16(16), $table->int16(18), $table->int16(20), $table->int16(22), $count);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed vertical header.', 0, $error);
        }
        $metrics = [];
        $advance = 0;
        for ($glyph = 0; $glyph < $font->glyphCount(); ++$glyph) {
            if ($glyph < $count) {
                $advance = $metricsTable->uint16(4 * $glyph);
                $bearing = $metricsTable->int16(4 * $glyph + 2);
            } else {
                $bearing = $metricsTable->int16(4 * $count + 2 * ($glyph - $count));
            }
            $metrics[] = new LongVerMetric($advance, $bearing);
        }

        return new VerticalMetrics($font->table(new Tag('head'))->uint16(18), $header, $metrics);
    }
}
