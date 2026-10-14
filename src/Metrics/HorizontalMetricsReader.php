<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Value\Tag;

/** Reads the default-instance hmtx metrics, without applying HVAR or gvar. */
final readonly class HorizontalMetricsReader
{
    public function read(SfntFont $font): HorizontalMetrics
    {
        $head = $font->table(new Tag('head'));
        $hhea = $font->table(new Tag('hhea'));
        $hmtx = $font->table(new Tag('hmtx'));
        $hhea->bytes(0, 36);
        if ($hhea->uint32(0) !== 0x00010000 || $hhea->int16(32) !== 0) {
            throw new MalformedFontException('Unsupported horizontal header version or metric data format.');
        }
        $count = $hhea->uint16(34);
        if ($count < 1 || $count > $font->glyphCount()) {
            throw new MalformedFontException('numberOfHMetrics must be between one and numGlyphs.');
        }
        $requiredBytes = 4 * $count + 2 * ($font->glyphCount() - $count);
        if ($hmtx->size() !== $requiredBytes) {
            throw new MalformedFontException('The horizontal metrics length does not match numGlyphs and numberOfHMetrics.');
        }
        $metrics = [];
        $advance = 0;
        for ($glyph = 0; $glyph < $font->glyphCount(); ++$glyph) {
            if ($glyph < $count) {
                $advance = $hmtx->uint16(4 * $glyph);
                $bearing = $hmtx->int16(4 * $glyph + 2);
            } else {
                $bearing = $hmtx->int16(4 * $count + 2 * ($glyph - $count));
            }
            $metrics[] = new LongHorMetric($advance, $bearing);
        }

        return new HorizontalMetrics($head->uint16(18), $metrics);
    }
}
