<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Metrics\HorizontalMetrics;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Metrics\LongHorMetric;
use PDFToolkit\OpenType\Metrics\VerticalMetricsReader;
use PDFToolkit\OpenType\Outline\CompositeGlyph;
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\GlyphBounds;
use PDFToolkit\OpenType\Outline\GlyphComponent;
use PDFToolkit\OpenType\Outline\MetricPhantomProvider;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\QuadraticOutline;
use PDFToolkit\OpenType\Outline\SimpleGlyph;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Outline\TrueTypeOutlines;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\ControlValueReader;
use PDFToolkit\OpenType\Variation\CvarReader;
use PDFToolkit\OpenType\Variation\GvarInstance;
use PDFToolkit\OpenType\Variation\GvarReader;
use PDFToolkit\OpenType\Variation\GvarTable;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\TupleDelta;
use PDFToolkit\OpenType\Variation\TupleRegion;
use PDFToolkit\OpenType\Variation\TupleVariation;
use PDFToolkit\OpenType\Variation\TupleVariationReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GvarTest extends TestCase
{
    public function testSparseContoursNestedComponentsAndPhantoms(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitGvar.ttf'));
        $outlines = (new TrueTypeOutlineReader())->read($font);
        $instance = new GvarInstance((new GvarReader())->read($font, $outlines), new NormalizedCoordinates([1, -0.5]));
        $metrics = new MetricPhantomProvider((new HorizontalMetricsReader())->read($font), (new VerticalMetricsReader())->read($font));
        $engine = new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled);
        $simple = $engine->glyph($outlines, new GlyphId(2), $metrics, $instance);
        self::assertSame([150.0, 330.0], [$simple->outline->points[1]->x, $simple->outline->points[1]->y]);
        self::assertSame(720.0, $simple->phantoms->advanceWidth());
        self::assertSame(1120.0, $simple->phantoms->advanceHeight());
        $scaled = $engine->glyph($outlines, new GlyphId(5), $metrics, $instance);
        self::assertSame([55.0, 30.0], [$scaled->outline->points[0]->x, $scaled->outline->points[0]->y]);
        $attached = $engine->outline($outlines, new GlyphId(7), $instance);
        self::assertEquals($attached->points[1], $attached->points[3]);
        self::assertCount(8, $engine->outline($outlines, new GlyphId(8), $instance)->points);
        self::assertSame([], $engine->glyph($outlines, new GlyphId(1), $metrics, $instance)->outline->points);
    }

    public function testCvarLeavesOmittedValuesUntouchedAndRetainsFractions(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitGvar.ttf'));
        $defaults = (new ControlValueReader())->read($font->table(new Tag('cvt ')));
        $cvar = (new CvarReader())->read($font);
        self::assertSame([60.0, 110.0, -20.0, 240.0], $cvar->instance($defaults, new NormalizedCoordinates([1, -0.5]))->values);
        self::assertSame([45.0, 80.0, -22.5, 210.0], $cvar->instance($defaults, new NormalizedCoordinates([0.25, 0]))->values);
        self::assertSame([40.0, 80.0, -20.0, 200.0], $defaults->values);
    }

    #[DataProvider('iupCases')]
    public function testIupInterpolationUsesOriginalPerContourCoordinates(array $xs, array $indices, array $dx, array $expected): void
    {
        $points = array_map(fn ($x) => new OutlinePoint($x, 0), $xs);
        $points = array_merge($points, array_fill(0, 4, new OutlinePoint(0, 0)));
        $tuple = new TupleVariation(new TupleRegion(new NormalizedCoordinates([1])), $indices, array_map(fn ($x) => new TupleDelta($x), $dx));
        $instance = new GvarInstance(new GvarTable(1, [[$tuple]]), new NormalizedCoordinates([1]));
        $deltas = $instance->deltas(new GlyphId(0), $points, [count($xs) - 1]);
        self::assertSame($expected, array_map(fn ($d) => $d->x, array_slice($deltas, 0, count($xs))));
        self::assertSame([0.0, 0.0, 0.0, 0.0], array_map(fn ($d) => $d->x, array_slice($deltas, -4)));
    }

    public static function iupCases(): iterable
    {
        yield 'none' => [[0, 50, 100], [], [], [0.0, 0.0, 0.0]];
        yield 'one' => [[0, 50, 100], [1], [20], [20.0, 20.0, 20.0]];
        yield 'linear' => [[0, 50, 100], [0, 2], [20, 60], [20.0, 40.0, 60.0]];
        yield 'coincident same delta' => [[0, 50, 0], [0, 2], [20, 20], [20.0, 20.0, 20.0]];
        yield 'coincident different deltas' => [[0, 50, 0], [0, 2], [20, 60], [20.0, 0.0, 60.0]];
        yield 'outside lower' => [[0, -50, 100], [0, 2], [20, 60], [20.0, 20.0, 60.0]];
        yield 'outside upper and reversed' => [[100, 150, 0], [0, 2], [20, 60], [20.0, 20.0, 60.0]];
    }

    public function testTupleScalarSaturationIntermediateAndNeutralAxes(): void
    {
        $region = new TupleRegion(new NormalizedCoordinates([0.5, 0]));
        self::assertSame(1.0, $region->scalar(new NormalizedCoordinates([1, -1])));
        self::assertSame(0.0, $region->scalar(new NormalizedCoordinates([-0.5, 1])));
        $region = new TupleRegion(new NormalizedCoordinates([0.5]), new NormalizedCoordinates([0.25]), new NormalizedCoordinates([1]));
        self::assertSame(0.5, $region->scalar(new NormalizedCoordinates([0.75])));
        self::assertSame(0.0, $region->scalar(new NormalizedCoordinates([0])));
    }

    public function testPhantomPointAttachmentRequiresExplicitMetrics(): void
    {
        $simple = new SimpleGlyph(new GlyphBounds(0, 0, 100, 100), new QuadraticOutline([new OutlinePoint(0, 0), new OutlinePoint(100, 0), new OutlinePoint(100, 100)], [2]));
        $composite = new CompositeGlyph(new GlyphBounds(0, 0, 700, 100), [new GlyphComponent(new GlyphId(0), 2, 0, 0), new GlyphComponent(new GlyphId(0), 0, 4, 3)]);
        $outlines = new TrueTypeOutlines([$simple, $composite]);
        $metrics = new MetricPhantomProvider(new HorizontalMetrics(1000, [new LongHorMetric(500, 0), new LongHorMetric(600, 0)]));
        $result = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->glyph($outlines, new GlyphId(1), $metrics);
        self::assertSame(600.0, $result->outline->points[3]->x);
    }

    public function testUseMyMetricsSelectsLastComponentPhantomsBeforeCompositeDeltas(): void
    {
        $simple = new SimpleGlyph(new GlyphBounds(0, 0, 100, 100), new QuadraticOutline([new OutlinePoint(0, 0), new OutlinePoint(100, 0), new OutlinePoint(100, 100)], [2]));
        $composite = new CompositeGlyph(new GlyphBounds(0, 0, 100, 100), [new GlyphComponent(new GlyphId(0), 0x202, 0, 0)]);
        $outlines = new TrueTypeOutlines([$simple, $composite]);
        $region = new TupleRegion(new NormalizedCoordinates([1]));
        $variation = new GvarInstance(new GvarTable(1, [[new TupleVariation($region, [4], [new TupleDelta(20)])], [new TupleVariation($region, [2], [new TupleDelta(80)])]]), new NormalizedCoordinates([1]));
        $metrics = new MetricPhantomProvider(new HorizontalMetrics(1000, [new LongHorMetric(500, 0), new LongHorMetric(600, 0)]));
        $result = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->glyph($outlines, new GlyphId(1), $metrics, $variation);
        self::assertSame(520.0, $result->phantoms->advanceWidth());
    }

    public function testLongAndShortGvarGlyphOffsetsAreEquivalent(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitGvar.ttf'));
        $outlines = (new TrueTypeOutlineReader())->read($font);
        $tables = [];
        foreach ($font->records() as $record) {
            $tables[$record->tag->value] = $record->data()->bytes(0, $record->length);
        }
        $table = $font->table(new Tag('gvar'));
        self::assertSame(0, $table->uint16(14));
        $extra = 2 * ($font->glyphCount() + 1);
        $header = substr($tables['gvar'], 0, 20);
        $header = substr_replace($header, pack('N', $table->uint32(8) + $extra), 8, 4);
        $header = substr_replace($header, pack('nN', 1, $table->uint32(16) + $extra), 14, 6);
        for ($index = 0; $index <= $font->glyphCount(); ++$index) {
            $header .= pack('N', 2 * $table->uint16(20 + 2 * $index));
        }
        $tables['gvar'] = $header.substr($tables['gvar'], 20 + $extra);
        $wide = (new SfntReader())->read(FontFixture::build($tables));
        self::assertEquals((new GvarReader())->read($font, $outlines), (new GvarReader())->read($wide, $outlines));
    }

    #[DataProvider('badStores')]
    public function testMalformedTupleStoresFailAtTheirBoundaries(string $bytes, bool $twoDimensions): void
    {
        $this->expectException(MalformedFontException::class);
        (new TupleVariationReader())->read(new BinaryView($bytes), 1, 4, [], $twoDimensions, new LayoutReadBudget(100));
    }

    public static function badStores(): iterable
    {
        yield 'reserved count flags' => [pack('nn', 0x1001, 4), true];
        yield 'data in header' => [pack('nnnnn', 1, 4, 2, 0x8000, 0x4000)."\x83\x83", true];
        yield 'missing shared peak' => [pack('nnnn', 1, 8, 2, 0)."\x83\x83", true];
        yield 'reserved header flag' => [pack('nnnnn', 1, 10, 2, 0x9000, 0x4000)."\x83\x83", true];
        yield 'missing cvar peak' => [pack('Nnnnn', 0x10000, 1, 12, 1, 0)."\x83", false];
        yield 'delta run overflow' => [pack('nnnnn', 1, 10, 2, 0x8000, 0x4000)."\x84\x83", true];
        yield 'duplicate points' => [pack('nnnnn', 1, 10, 6, 0xa000, 0x4000)."\x02\x01\0\0\x81\x81", true];
        yield 'point beyond glyph' => [pack('nnnnn', 1, 10, 5, 0xa000, 0x4000)."\x01\0\x04\x80\x80", true];
        yield 'tuple beyond normalized range' => [pack('nnnnn', 1, 10, 2, 0x8000, 0x7fff)."\x83\x83", true];
        yield 'truncated delta' => [pack('nnnnn', 1, 10, 2, 0x8000, 0x4000)."\x43\x00", true];
    }

    public function testExecutionBudgetBoundsRepeatedTupleInterpolation(): void
    {
        $region = new TupleRegion(new NormalizedCoordinates([1]));
        $tuple = new TupleVariation($region, [0], [new TupleDelta(1)]);
        $instance = new GvarInstance(new GvarTable(1, [[$tuple, $tuple]]), new NormalizedCoordinates([1]), new OutlineLimits(maxOperations: 8));
        $this->expectException(MalformedFontException::class);
        $instance->deltas(new GlyphId(0), array_fill(0, 4, new OutlinePoint(0, 0)), []);
    }
}
