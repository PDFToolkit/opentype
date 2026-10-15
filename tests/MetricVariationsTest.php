<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Metrics\VerticalOriginsReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\DeltaSetIndexMapReader;
use PDFToolkit\OpenType\Variation\GlyphMetricAxis;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationInstance;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationsReader;
use PDFToolkit\OpenType\Variation\MvarInstance;
use PDFToolkit\OpenType\Variation\MvarReader;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetricVariationsTest extends TestCase
{
    public function testNativeHorizontalVerticalAndFontWideMetricDeltas(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMetricVariations.ttf'));
        $horizontal = (new GlyphMetricVariationsReader())->read($font->table(new Tag('HVAR')), GlyphMetricAxis::Horizontal, $font->glyphCount(), 2);
        $h = new GlyphMetricVariationInstance($horizontal, new NormalizedCoordinates([0.5, -0.5]));
        self::assertSame(30.0, $h->advanceDelta(new GlyphId(0)));
        self::assertSame(50.0, $h->firstBearingDelta(new GlyphId(0)));
        self::assertNull($h->secondBearingDelta(new GlyphId(0)));
        self::assertNull($h->verticalOriginDelta(new GlyphId(0)));
        $vertical = (new GlyphMetricVariationsReader())->read($font->table(new Tag('VVAR')), GlyphMetricAxis::Vertical, $font->glyphCount(), 2);
        $v = new GlyphMetricVariationInstance($vertical, new NormalizedCoordinates([0.5, -0.5]));
        self::assertSame(30.0, $v->advanceDelta(new GlyphId(0)));
        self::assertSame(-10.0, $v->firstBearingDelta(new GlyphId(0)));
        self::assertSame(50.0, $v->secondBearingDelta(new GlyphId(0)));
        $m = new MvarInstance((new MvarReader())->read($font->table(new Tag('MVAR')), 2), new NormalizedCoordinates([0.5, -0.5]));
        self::assertSame(830.0, $m->adjust(new Tag('hasc'), 800));
        self::assertSame(0.0, $m->delta(new Tag('XXXX')));
        $default = (new VerticalMetricsReader())->read($font);
        self::assertSame(1000, $default->advanceHeight(new GlyphId(0)));
        self::assertSame(1100, $default->advanceHeight(new GlyphId($font->glyphCount() - 1)));
        self::assertSame(50, $default->topSideBearing(new GlyphId(0)));
        self::assertLessThan($font->glyphCount(), $default->header->numOfLongVerMetrics);
    }

    #[DataProvider('packedMappings')]
    public function testEveryPackedWidthAndCountFormat(int $format, int $width): void
    {
        $prefix = pack('CC', $format, (($width - 1) << 4) | 3).($format === 0 ? pack('n', 2) : pack('N', 2));
        $bytes = $prefix.substr(pack('N', 0x12), 4 - $width).substr(pack('N', 0x23), 4 - $width);
        $map = (new DeltaSetIndexMapReader())->read(new BinaryView($bytes), new LayoutReadBudget(10));
        self::assertSame(1, $map->index(0)->outerIndex);
        self::assertSame(2, $map->index(0)->innerIndex);
        self::assertSame(2, $map->index(99999)->outerIndex);
        self::assertSame(3, $map->index(99999)->innerIndex);
    }

    public static function packedMappings(): iterable
    {
        foreach ([0, 1] as $format) {
            foreach ([1, 2, 3, 4] as $width) {
                yield [$format, $width];
            }
        }
    }

    public function testExplicitNoVariationSentinelAndImplicitAdvanceMapping(): void
    {
        $map = (new DeltaSetIndexMapReader())->read(new BinaryView(pack('CCnN', 0, 0x3F, 1, 0xFFFFFFFF)), new LayoutReadBudget(2));
        self::assertSame(65535, $map->index(0)->outerIndex);
        self::assertSame(65535, $map->index(0)->innerIndex);
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMetricVariations.ttf'));
        $view = $font->table(new Tag('HVAR'));
        $bytes = substr_replace($view->bytes(0, $view->size()), pack('N', 0), 8, 4);
        $table = (new GlyphMetricVariationsReader())->read(new BinaryView($bytes), GlyphMetricAxis::Horizontal, 4, 2);
        $instance = new GlyphMetricVariationInstance($table, new NormalizedCoordinates([1, 0]));
        self::assertSame(100.0, $instance->advanceDelta(new GlyphId(0)));
        self::assertSame(-80.0, $instance->advanceDelta(new GlyphId(1)));
        self::assertSame(0.0, $instance->advanceDelta(new GlyphId(3)));
    }

    public function testEmptyMvarHasNoImplicitStore(): void
    {
        $table = (new MvarReader())->read(new BinaryView(pack('n*', 1, 0, 0, 8, 0, 0)), 2);
        self::assertSame(12.0, (new MvarInstance($table, new NormalizedCoordinates([0, 0])))->adjust(new Tag('hasc'), 12));
    }

    public function testOriginOverridesAreOnlyConsumedForCffFonts(): void
    {
        $tables = FontFixture::tables(4, 0x4F54544F);
        $tables['VORG'] = pack('n*', 1, 0, 800, 2, 1, 810, 3, -20);
        $font = (new SfntReader())->read(FontFixture::build($tables, 0x4F54544F));
        $origins = (new VerticalOriginsReader())->read($font);
        self::assertSame(800, $origins->originFor(new GlyphId(0)));
        self::assertSame(810, $origins->originFor(new GlyphId(1)));
        self::assertSame(-20, $origins->originFor(new GlyphId(3)));
        $this->expectException(\PDFToolkit\OpenType\Exception\UnsupportedFontException::class);
        (new VerticalOriginsReader())->read((new SfntReader())->read(FontFixture::build(FontFixture::tables())));
    }

    #[DataProvider('malformedMaps')]
    public function testMalformedPackedMapsFailBeforeAllocation(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new DeltaSetIndexMapReader())->read(new BinaryView($bytes), new LayoutReadBudget(100));
    }

    public static function malformedMaps(): iterable
    {
        yield 'empty' => [pack('CCn', 0, 0, 0)];
        yield 'reserved' => [pack('CCnC', 0, 0xC0, 1, 0)];
        yield 'inner wider than entry' => [pack('CCnC', 0, 15, 1, 0)];
        yield 'budget' => [pack('CCN', 1, 0, 0xFFFFFFFF)];
        yield 'truncated' => [pack('CCnC', 0, 0x30, 1, 0)];
        yield 'outer exceeds uint16' => [pack('CCnN', 0, 0x30, 1, 0xFFFFFFFF)];
    }

    #[DataProvider('invalidMetricTables')]
    public function testOffsetsAndRelatedAxesAreValidated(string $tag, int $offset, string $replacement): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMetricVariations.ttf'));
        $view = $font->table(new Tag($tag));
        $bytes = substr_replace($view->bytes(0, $view->size()), $replacement, $offset, strlen($replacement));
        $this->expectException(MalformedFontException::class);
        if ($tag === 'MVAR') {
            (new MvarReader())->read(new BinaryView($bytes), 2);
        } else {
            (new GlyphMetricVariationsReader())->read(new BinaryView($bytes), $tag === 'HVAR' ? GlyphMetricAxis::Horizontal : GlyphMetricAxis::Vertical, $font->glyphCount(), 2);
        }
    }

    public static function invalidMetricTables(): iterable
    {
        yield ['HVAR', 4, pack('N', 1)];
        yield ['VVAR', 8, pack('N', 0xFFFFFFFF)];
        yield ['MVAR', 6, pack('n', 7)];
        yield ['MVAR', 10, pack('n', 0)];
        yield ['MVAR', 12, 'zzzz'];
        yield ['MVAR', 16, pack('n', 300)];
    }

    public function testAllNewVariationReadersApplyAggregateBudgets(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMetricVariations.ttf'));
        $this->expectException(MalformedFontException::class);
        (new GlyphMetricVariationsReader(new ReadLimits(maxLayoutRecords: 2)))->read($font->table(new Tag('HVAR')), GlyphMetricAxis::Horizontal, $font->glyphCount(), 2);
    }
}
