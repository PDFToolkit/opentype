<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Outline\ComponentTransform;
use PDFToolkit\OpenType\Outline\CompositeGlyph;
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\GlyphBounds;
use PDFToolkit\OpenType\Outline\GlyphComponent;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\PathOperator;
use PDFToolkit\OpenType\Outline\QuadraticOutline;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\SimpleGlyph;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Outline\TrueTypeOutlines;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrueTypeOutlinesTest extends TestCase
{
    public function testSimpleAndNestedCompositeOutlinesRemainIndependentOfInstructions(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitOutlines.ttf'));
        $outlines = (new TrueTypeOutlineReader())->read($font);
        self::assertSame('b00021', bin2hex($outlines->glyph(new GlyphId(2))->instructions()));
        $engine = new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled);
        $simple = $engine->outline($outlines, new GlyphId(2));
        self::assertCount(4, $simple->points);
        self::assertSame([3], $simple->endPoints);
        self::assertFalse($simple->points[1]->onCurve);
        $scaled = $engine->outline($outlines, new GlyphId(5));
        self::assertSame([30.0, -15.0], [$scaled->points[0]->x, $scaled->points[0]->y]);
        $attached = $engine->outline($outlines, new GlyphId(7));
        self::assertSame([$attached->points[1]->x, $attached->points[1]->y], [$attached->points[3]->x, $attached->points[3]->y]);
        self::assertCount(8, $engine->outline($outlines, new GlyphId(8))->points);
    }

    public function testAllOffCurveContoursProduceImpliedOnCurveMidpoints(): void
    {
        $outline = new QuadraticOutline([new OutlinePoint(0, 100, false), new OutlinePoint(100, 200, false), new OutlinePoint(200, 100, false), new OutlinePoint(100, 0, false)], [3]);
        $path = (new QuadraticPathBuilder())->build($outline);
        self::assertSame([PathOperator::Move, PathOperator::Quadratic, PathOperator::Quadratic, PathOperator::Quadratic, PathOperator::Quadratic, PathOperator::Close], array_map(fn ($c) => $c->operator, $path->commands));
        self::assertSame(50.0, $path->commands[0]->points[0]->x);
        self::assertSame(50.0, $path->commands[0]->points[0]->y);
        self::assertEquals($path->commands[0]->points[0], $path->commands[4]->points[1]);
    }

    public function testShortAndLongLocaOffsetsProduceIdenticalOutlines(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitOutlines.ttf'));
        $tables = [];
        foreach ($font->records() as $record) {
            $tables[$record->tag->value] = $record->data()->bytes(0, $record->length);
        }
        self::assertSame(0, $font->table(new Tag('head'))->int16(50));
        $tables['loca'] = '';
        for ($i = 0; $i <= $font->glyphCount(); ++$i) {
            $tables['loca'] .= pack('N', $font->table(new Tag('loca'))->uint16(2 * $i) * 2);
        }
        $tables['head'] = substr_replace($tables['head'], pack('n', 1), 50, 2);
        $long = (new SfntReader())->read(FontFixture::build($tables));
        self::assertEquals((new TrueTypeOutlineReader())->read($font), (new TrueTypeOutlineReader())->read($long));
    }

    public function testZeroContourGlyphCanRetainInstructions(): void
    {
        $tables = FontFixture::tables(1);
        $tables['glyf'] = pack('n*', 0, 0, 0, 0, 0, 2)."\xB0\0";
        $tables['loca'] = pack('n*', 0, 7);
        $font = (new SfntReader())->read(FontFixture::build($tables));
        $outlines = (new TrueTypeOutlineReader())->read($font);
        self::assertSame("\xB0\0", $outlines->glyph(new GlyphId(0))->instructions());
        self::assertSame([], (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->outline($outlines, new GlyphId(0))->points);
    }

    #[DataProvider('invalidGlyphs')]
    public function testMalformedGlyphBytesFailWithinTheirLocaBoundary(string $data): void
    {
        $tables = FontFixture::tables(1);
        $tables['glyf'] = $data;
        $tables['head'] = substr_replace($tables['head'], pack('n', 1), 50, 2);
        $tables['loca'] = pack('N*', 0, strlen($data));
        $this->expectException(MalformedFontException::class);
        (new TrueTypeOutlineReader())->read((new SfntReader())->read(FontFixture::build($tables)));
    }

    public static function invalidGlyphs(): iterable
    {
        yield 'header' => ["\0"];
        yield 'instruction span' => [pack('n*', 0, 0, 0, 0, 0, 65535)];
        yield 'unordered contours' => [pack('n*', 2, 0, 0, 0, 0, 2, 1, 0)];
        yield 'repeated flags exceed points' => [pack('n*', 1, 0, 0, 0, 0, 0, 0).pack('CC', 0x39, 2)];
        yield 'reserved simple flag' => [pack('n*', 1, 0, 0, 0, 0, 0, 0).pack('C', 0xB1)];
        yield 'truncated coordinates' => [pack('n*', 1, 0, 0, 0, 0, 0, 0).pack('C', 1)];
        yield 'component glyph outside font' => [pack('n*', -1, 0, 0, 0, 0, 3, 1, 0, 0)];
        yield 'reserved component flag' => [pack('n*', -1, 0, 0, 0, 0, 0x13, 0, 0, 0)];
        yield 'contradictory transforms' => [pack('n*', -1, 0, 0, 0, 0, 0xCB, 0, 0, 0)];
    }

    public function testComponentCycleIsRejectedWithoutRecursingForever(): void
    {
        $glyph = new CompositeGlyph(new GlyphBounds(0, 0, 0, 0), [new GlyphComponent(new GlyphId(0), 2, 0, 0)]);
        $this->expectException(MalformedFontException::class);
        (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->outline(new TrueTypeOutlines([$glyph]), new GlyphId(0));
    }

    public function testReusedAcyclicChildrenConsumeAnExpansionBudget(): void
    {
        $bounds = new GlyphBounds(0, 0, 0, 0);
        $simple = new SimpleGlyph($bounds, new QuadraticOutline([new OutlinePoint(0, 0)], [0]));
        $component = new GlyphComponent(new GlyphId(0), 2, 0, 0);
        $font = new TrueTypeOutlines([$simple, new CompositeGlyph($bounds, [$component, $component, $component])]);
        $this->expectException(MalformedFontException::class);
        (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled, new OutlineLimits(maxExpandedPoints: 2)))->outline($font, new GlyphId(1));
    }

    public function testUndeclaredComponentScalingPolicyRemainsExplicit(): void
    {
        $bounds = new GlyphBounds(0, 0, 100, 100);
        $simple = new SimpleGlyph($bounds, new QuadraticOutline([new OutlinePoint(100, 100)], [0]));
        $font = new TrueTypeOutlines([$simple, new CompositeGlyph($bounds, [new GlyphComponent(new GlyphId(0), 2, 20, 40, new ComponentTransform(0.5, 0, 0, 0.5))])]);
        self::assertSame(70.0, (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))->outline($font, new GlyphId(1))->points[0]->x);
        self::assertSame(60.0, (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Scaled))->outline($font, new GlyphId(1))->points[0]->x);
        $this->expectException(\PDFToolkit\OpenType\Exception\UnsupportedFontException::class);
        (new TrueTypeOutlineEngine(CompositeOffsetPolicy::RejectAmbiguous))->outline($font, new GlyphId(1));
    }

    public function testAggregateReaderLimitsApplyBeforePointAllocation(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitOutlines.ttf'));
        $this->expectException(MalformedFontException::class);
        (new TrueTypeOutlineReader(new OutlineLimits(maxRecords: 10)))->read($font);
    }
}
