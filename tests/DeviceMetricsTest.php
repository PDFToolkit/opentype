<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\DeviceMetrics\HorizontalDeviceMetricsReader;
use PDFToolkit\OpenType\DeviceMetrics\LinearThresholdReader;
use PDFToolkit\OpenType\DeviceMetrics\VerticalDeviceMetricsReader;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceMetricsTest extends TestCase
{
    public function testPrecomputedMetricsAreSelectedWithoutInvokingAScaler(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitDeviceMetrics.ttf'));
        $horizontal = (new HorizontalDeviceMetricsReader())->read($font->table(new Tag('hdmx')), $font->glyphCount());
        self::assertSame(9, $horizontal->atPpem(12)->width(new GlyphId(2)));
        self::assertNull($horizontal->atPpem(13));
        $thresholds = (new LinearThresholdReader())->read($font->table(new Tag('LTSH')), $font->glyphCount());
        self::assertFalse($thresholds->declaresLinear(new GlyphId(2), 49));
        self::assertTrue($thresholds->declaresLinear(new GlyphId(2), 50));
        $vertical = (new VerticalDeviceMetricsReader())->read($font->table(new Tag('VDMX')));
        self::assertSame($vertical->ratios[0]->group, $vertical->ratios[1]->group);
        self::assertSame(10, $vertical->select(72, 72, 1)->atPpem(12)->maximum);
        self::assertSame(10, $vertical->select(144, 72, 1)->atPpem(12)->maximum);
        self::assertSame(11, $vertical->select(72, 96, 1)->atPpem(12)->maximum);
        self::assertNull($vertical->select(72, 72, 0));
        self::assertNull($vertical->select(72, 72, 1)->atPpem(10));
    }

    #[DataProvider('badHorizontal')]
    public function testHorizontalRecordBoundsAndPaddingAreValidated(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new HorizontalDeviceMetricsReader())->read(new BinaryView($bytes), 1);
    }
    public static function badHorizontal(): iterable
    {
        yield 'misaligned size' => [pack('nnN', 0, 1, 3)."\x0c\x05\x05"];
        yield 'overflowing count' => [pack('nnN', 0, 65535, 4)];
        yield 'width exceeds maximum' => [pack('nnN', 0, 1, 4)."\x0c\x04\x05\0"];
        yield 'nonzero padding' => [pack('nnN', 0, 1, 4)."\x0c\x05\x05\x01"];
        yield 'duplicate size' => [pack('nnN', 0, 2, 4).str_repeat("\x0c\x05\x05\0", 2)];
    }
    public function testDeviceBudgetsAreAggregateNotJustPerSubtable(): void
    {
        $this->expectException(MalformedFontException::class);
        (new HorizontalDeviceMetricsReader(new ReadLimits(maxLayoutRecords: 3)))->read(new BinaryView(pack('nnN', 0, 2, 4)."\x0c\x05\x05\0\x10\x06\x06\0"), 1);
    }
    public function testThresholdsCannotInventMissingGlyphEntries(): void
    {
        $this->expectException(MalformedFontException::class);
        (new LinearThresholdReader())->read(new BinaryView(pack('nnC', 0, 1, 50)), 2);
    }
    #[DataProvider('badVertical')]
    public function testVerticalOffsetsCountsAndOrderingAreValidated(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new VerticalDeviceMetricsReader())->read(new BinaryView($bytes));
    }
    public static function badVertical(): iterable
    {
        yield 'group inside header' => [pack('nnnCCCCn', 1, 1, 1, 1, 1, 1, 1, 2)];
        yield 'missing group' => [pack('nnnCCCCn', 1, 2, 1, 1, 1, 1, 1, 12).pack('nCCnnn', 1, 12, 12, 12, 10, -3)];
        yield 'truncated heights' => [pack('nnnCCCCn', 1, 1, 1, 1, 1, 1, 1, 12).pack('nCC', 2, 12, 12)];
        yield 'descending heights' => [pack('nnnCCCCn', 1, 1, 1, 1, 1, 1, 1, 12).pack('nCCnnnnnn', 2, 8, 12, 12, 10, -3, 8, 7, -2)];
        yield 'inverted bounds' => [pack('nnnCCCCn', 1, 1, 1, 1, 1, 1, 1, 12).pack('nCCnnn', 1, 12, 12, 12, -3, 10)];
        yield 'invalid default' => [pack('nnnCCCCn', 1, 1, 1, 1, 0, 0, 1, 12).pack('nCCnnn', 1, 12, 12, 12, 10, -3)];
    }
}
