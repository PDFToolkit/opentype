<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PHPUnit\Framework\TestCase;

final class MetricTest extends TestCase
{
    public function testReadsSignedBearingsAndRepeatsLastAdvanceWidth(): void
    {
        $font = (new SfntReader())->read(FontFixture::build(FontFixture::tables()));
        $metrics = (new HorizontalMetricsReader())->read($font);
        self::assertSame(1000, $metrics->unitsPerEm);
        self::assertSame(4, $metrics->glyphCount());
        self::assertSame(500, $metrics->advanceWidth(new GlyphId(0)));
        self::assertSame(600, $metrics->advanceWidth(new GlyphId(3)));
        self::assertSame(-20, $metrics->leftSideBearing(new GlyphId(1)));
        self::assertSame(-5, $metrics->leftSideBearing(new GlyphId(2)));
        self::assertSame(20, $metrics->leftSideBearing(new GlyphId(3)));
    }

    public function testMetricLookupRejectsForeignGlyph(): void
    {
        $metrics = (new HorizontalMetricsReader())->read((new SfntReader())->read(FontFixture::build(FontFixture::tables())));
        $this->expectException(\OutOfBoundsException::class);
        $metrics->advanceWidth(new GlyphId(4));
    }

    public function testInvalidNumberOfHMetricsIsRejected(): void
    {
        $tables = FontFixture::tables();
        $tables['hhea'] = substr_replace($tables['hhea'], pack('n', 5), 34, 2);
        $font = (new SfntReader())->read(FontFixture::build($tables));
        $this->expectException(MalformedFontException::class);
        (new HorizontalMetricsReader())->read($font);
    }

    public function testTruncatedMetricArrayIsRejected(): void
    {
        $tables = FontFixture::tables();
        $tables['hmtx'] = substr($tables['hmtx'], 0, -1);
        $font = (new SfntReader())->read(FontFixture::build($tables));
        $this->expectException(MalformedFontException::class);
        (new HorizontalMetricsReader())->read($font);
    }

    public function testMissingMetricTableIsRejected(): void
    {
        $tables = FontFixture::tables();
        unset($tables['hmtx']);
        $font = (new SfntReader())->read(FontFixture::build($tables));
        $this->expectException(MalformedFontException::class);
        (new HorizontalMetricsReader())->read($font);
    }
}
