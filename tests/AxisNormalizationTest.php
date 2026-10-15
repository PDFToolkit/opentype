<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\AvarReader;
use PDFToolkit\OpenType\Variation\AvarTable;
use PDFToolkit\OpenType\Variation\AxisSegmentMap;
use PDFToolkit\OpenType\Variation\AxisValueMap;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\FvarTable;
use PDFToolkit\OpenType\Variation\UserCoordinates;
use PDFToolkit\OpenType\Variation\VariationAxis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AxisNormalizationTest extends TestCase
{
    public function testTwoAsymmetricAxesKeepOriginalOrderAndApplySegmentMaps(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAxes.ttf'));
        $fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
        $avar = (new AvarReader())->read($font->table(new Tag('avar')), count($fvar->axes));
        self::assertSame(['wght', 'wdth'], array_map(fn ($axis) => $axis->tag->value, $fvar->axes));
        self::assertFalse($fvar->axes[0]->hidden());
        self::assertTrue($fvar->axes[1]->hidden());
        self::assertSame([0.0, 0.0], $fvar->normalize(new UserCoordinates(), $avar)->values);
        self::assertSame([0.25, -0.25], $fvar->normalize(new UserCoordinates(['wdth' => 81.25, 'wght' => 650.0]), $avar)->values);
        self::assertSame([1.0, -1.0], $fvar->normalize(new UserCoordinates(['wght' => 10000.0, 'wdth' => -99.0]), $avar)->values);
        self::assertSame([0.5, -0.5], $fvar->normalize($fvar->instanceCoordinates(0))->values);
        self::assertSame(259, $fvar->instances[0]->postScriptNameId);
    }

    public function testDegenerateAxisAndAbsentCoordinatesUseDefaultWithoutDivisionByZero(): void
    {
        $fvar = new FvarTable([new VariationAxis(new Tag('TEST'), 10, 10, 10, 0, 256)], []);
        self::assertSame([0.0], $fvar->normalize(new UserCoordinates(['TEST' => 99.0]))->values);
        self::assertSame([], (new FvarTable([], []))->normalize(new UserCoordinates())->values);
        $left = new VariationAxis(new Tag('TEST'), 10, 10, 20, 0, 256);
        self::assertSame(0, $left->normalize(-99));
        self::assertSame(65536, $left->normalize(99));
    }

    public function testMissingMandatoryAvarAnchorsDisableMapping(): void
    {
        $map = new AxisSegmentMap([new AxisValueMap(-16384, -16384), new AxisValueMap(8192, 4096), new AxisValueMap(16384, 16384)]);
        self::assertFalse($map->active());
        self::assertSame(32768, $map->map(32768));
        self::assertSame(1234, (new AxisSegmentMap([]))->map(1234));
    }

    public function testUnknownAxesAreNotSilentlyMisassigned(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FvarTable([], []))->normalize(new UserCoordinates(['wght' => 650.0]));
    }

    public function testAvarAxisMismatchIsRejectedAtExecution(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FvarTable([], []))->normalize(new UserCoordinates(), new AvarTable([new AxisSegmentMap([])]));
    }

    #[DataProvider('invalidFvar')]
    public function testFvarOffsetsCountsSizesAndRangesAreChecked(int $offset, string $replacement): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAxes.ttf'));
        $table = $font->table(new Tag('fvar'));
        $bytes = substr_replace($table->bytes(0, $table->size()), $replacement, $offset, strlen($replacement));
        $this->expectException(MalformedFontException::class);
        (new FvarReader())->read(new BinaryView($bytes));
    }

    public static function invalidFvar(): iterable
    {
        yield 'offset in header' => [4, pack('n', 4)];
        yield 'offset beyond bytes' => [4, pack('n', 65535)];
        yield 'reserved' => [6, pack('n', 0)];
        yield 'axis size' => [10, pack('n', 19)];
        yield 'instance size' => [14, pack('n', 15)];
        yield 'record budget' => [12, pack('n', 65535)];
        yield 'default below minimum' => [24, pack('N', 0)];
        yield 'axis name id' => [34, pack('n', 2)];
        yield 'duplicate tag' => [36, 'wght'];
        yield 'instance outside axis' => [60, pack('N', 1000 * 65536)];
    }

    public function testFvarTruncationAndBudgetAreRejectedBeforeAllocation(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAxes.ttf'));
        $this->expectException(MalformedFontException::class);
        (new FvarReader(new ReadLimits(maxLayoutRecords: 2)))->read($font->table(new Tag('fvar')));
    }

    #[DataProvider('invalidAvar')]
    public function testAvarRejectsMalformedMaps(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new AvarReader())->read(new BinaryView($bytes), 1);
    }

    public static function invalidAvar(): iterable
    {
        yield 'wrong axes' => [pack('n*', 1, 0, 0, 2, 0, 0)];
        yield 'reserved' => [pack('n*', 1, 0, 1, 1, 0)];
        yield 'truncated maps' => [pack('n*', 1, 0, 0, 1, 3, 0, 0)];
        yield 'duplicate input' => [pack('n*', 1, 0, 0, 1, 2, 0, 0, 0, 0)];
        yield 'retrograde' => [pack('n*', 1, 0, 0, 1, 2, 0, 8192, 8192, 4096)];
        yield 'outside bounds' => [pack('n*', 1, 0, 0, 1, 1, 32767, 0)];
    }

    public function testFutureAvarVersionIsExplicitlyUnsupported(): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new AvarReader())->read(new BinaryView(pack('n*', 2, 0, 0, 0)), 0);
    }
}
