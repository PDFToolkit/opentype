<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Cff\CffCharacterSet;
use PDFToolkit\OpenType\Cff\CffCharacterSetReader;
use PDFToolkit\OpenType\Cff\CffDictionary;
use PDFToolkit\OpenType\Cff\CffDictReader;
use PDFToolkit\OpenType\Cff\CffFont;
use PDFToolkit\OpenType\Cff\CffFontDictionary;
use PDFToolkit\OpenType\Cff\CffFontMatrix;
use PDFToolkit\OpenType\Cff\CffIndex;
use PDFToolkit\OpenType\Cff\CffIndexReader;
use PDFToolkit\OpenType\Cff\CffNumberReader;
use PDFToolkit\OpenType\Cff\CffOutlineEngine;
use PDFToolkit\OpenType\Cff\CffPrivateDictionaryInstance;
use PDFToolkit\OpenType\Cff\CffRandomSource;
use PDFToolkit\OpenType\Cff\CffReader;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePath;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\PathCommand;
use PDFToolkit\OpenType\Outline\PathOperator;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CffTest extends TestCase
{
    public function testRealCffCharsetEncodingSubroutinesAndCompositeGeometry(): void
    {
        $font = (new CffReader())->read((new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitCff.otf')));
        $engine = new CffOutlineEngine();
        self::assertSame('subr', $font->characterSet->name(new GlyphId(13)));
        $subr = $engine->outline($font, new GlyphId(13));
        self::assertSame(700.0, $subr->width);
        self::assertSame([100.0, 100.0], [$subr->path->commands[2]->points[0]->x, $subr->path->commands[2]->points[0]->y]);
        self::assertSame(2, $engine->outline($font, new GlyphId(14))->stemCount);
        self::assertSame(15, $font->encoding->glyph(90)->value);
        self::assertNull($font->encoding->glyph(65));
        $composite = $engine->outline($font, new GlyphId(17));
        self::assertCount(8, $composite->path->commands);
        self::assertSame([100.0, 400.0], [$composite->path->commands[4]->points[0]->x, $composite->path->commands[4]->points[0]->y]);
    }

    public function testRealCff2ExplicitCoordinatesFdSelectAndBlend(): void
    {
        $font = (new CffReader())->read((new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitCff2.otf')));
        self::assertSame(1, $font->fontSelection[13]);
        $outline = (new CffOutlineEngine())->outline($font, new GlyphId(15), new NormalizedCoordinates([1, -1]));
        self::assertNull($outline->width);
        self::assertSame([160.0, 40.0], [$outline->path->commands[0]->points[0]->x, $outline->path->commands[0]->points[0]->y]);
        self::assertEquals($outline->path, $font->matrixFor(new GlyphId(15))->transform($outline->path, $font->unitsPerEm));
        $this->expectException(UnsupportedFontException::class);
        (new CffOutlineEngine())->outline($font, new GlyphId(15));
    }

    public function testPrivateDictionaryBlendPreservesStackAcrossOperators(): void
    {
        $font = (new CffReader())->read((new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitCff2.otf')));
        // BlueValues delta array: two defaults, two deltas per value, n=2, blend, BlueValues.
        $raw = self::numbers([0, 20, 10, 20, 30, 40, 2]).chr(23).chr(6);
        $dictionary = (new CffDictReader())->read(new BinaryView($raw), true, new LayoutReadBudget(100));
        $instance = new CffPrivateDictionaryInstance($font->variationStore, new NormalizedCoordinates([1, -1]));
        self::assertSame([30.0, 90.0], $instance->evaluate($dictionary)->operands(6));
        $this->expectException(MalformedFontException::class);
        $instance->evaluate((new CffDictReader())->read(new BinaryView(substr($raw, 0, -1).chr(19)), true, new LayoutReadBudget(100)));
    }

    public function testArithmeticTransientStorageAndExplicitRandom(): void
    {
        $program = self::numbers([10, 20])."\x0c\x0a".self::numbers([0])."\x0c\x14".self::numbers([0])."\x0c\x15".self::numbers([5])."\x0c\x18".self::numbers([50, 2])."\x0c\x0c\x15\x0c\x17".self::numbers([100])."\x0c\x18".self::numbers([0])."\x05\x0e";
        $source = new class () implements CffRandomSource {
            public function value(): float
            {
                return 0.5;
            }
        };
        $result = (new CffOutlineEngine())->outline($this->font($program), new GlyphId(0), random: $source);
        self::assertSame([150.0, 25.0], [$result->path->commands[0]->points[0]->x, $result->path->commands[0]->points[0]->y]);
        self::assertSame(200.0, $result->path->commands[1]->points[0]->x);
        $this->expectException(UnsupportedFontException::class);
        (new CffOutlineEngine())->outline($this->font($program), new GlyphId(0));
    }

    #[DataProvider('invalidPrograms')]
    public function testInvalidProgramsAreBoundedAndRejected(string $program, bool $cff2 = false): void
    {
        $this->expectException(MalformedFontException::class);
        (new CffOutlineEngine())->outline($this->font($program, $cff2), new GlyphId(0));
    }

    public static function invalidPrograms(): iterable
    {
        yield 'missing endchar' => [''];
        yield 'CFF2 endchar' => ["\x0e", true];
        yield 'CFF2 return' => ["\x0b", true];
        yield 'draw without move' => [self::numbers([1, 2])."\x05\x0e"];
        yield 'path arity' => [self::numbers([0, 0])."\x15".self::numbers([1])."\x05\x0e"];
        yield 'stem pair arity' => [self::numbers([0, 0])."\x15".self::numbers([1])."\x01\x0e"];
        yield 'truncated hintmask' => [self::numbers([1, 2])."\x01\x13"];
        yield 'stack overflow' => [str_repeat(chr(139), 49)."\x0e"];
        yield 'missing subroutine' => [self::numbers([-107])."\x0a\x0e"];
        yield 'underflow' => ["\x0c\x0a\x0e"];
        yield 'division zero' => [self::numbers([1, 0])."\x0c\x0c\x0e"];
        yield 'transient uninitialized' => [self::numbers([0])."\x0c\x15\x0e"];
        yield 'extra endchar bytes' => ["\x0e\x00"];
        yield 'CFF2 leftover stack' => [self::numbers([1]), true];
        yield 'absent variation store' => [self::numbers([0])."\x0f", true];
        yield 'noninteger code' => ["\xff\0\0\x80\0\x0a\x0e"];
    }

    public function testSubroutineAndCompositeCyclesAndOutputBudget(): void
    {
        $empty = new CffIndex([], 0);
        $dictionary = new CffDictionary([]);
        $program = new BinaryView(self::numbers([-107])."\x0a\x0e");
        $subr = new BinaryView(self::numbers([-107])."\x0a\x0b");
        $font = new CffFont(false, 1000, $dictionary, new CffIndex([$program], 0), $empty, [new CffFontDictionary($dictionary, $dictionary, new CffIndex([$subr], 0))], [0]);
        $this->expectException(MalformedFontException::class);
        (new CffOutlineEngine())->outline($font, new GlyphId(0));
    }

    public function testCompositeCycleUsesSharedExecutionBudget(): void
    {
        $dictionary = new CffDictionary([]);
        $empty = new CffIndex([], 0);
        $program = new BinaryView(self::numbers([0, 0, 65, 65]).chr(14));
        $font = new CffFont(false, 1000, $dictionary, new CffIndex([new BinaryView(chr(14)), $program], 0), $empty, [new CffFontDictionary($dictionary, $dictionary, $empty)], [0, 0], characterSet: new CffCharacterSet(false, [0, 34], $empty));
        $this->expectException(MalformedFontException::class);
        (new CffOutlineEngine())->outline($font, new GlyphId(1));
    }

    public function testOutputBudgetStopsRepeatedDrawing(): void
    {
        $this->expectException(MalformedFontException::class);
        (new CffOutlineEngine(new OutlineLimits(maxExpandedPoints: 2)))->outline($this->font(self::numbers([0, 0]).chr(21).self::numbers([1, 2, 3, 4]).chr(5).chr(14)), new GlyphId(0));
    }

    #[DataProvider('invalidIndices')]
    public function testIndexOffsetsAndCountsAreValidatedBeforeAllocation(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new CffIndexReader())->read(new BinaryView($bytes), 0, false, new LayoutReadBudget(10));
    }

    public static function invalidIndices(): iterable
    {
        yield 'count budget' => [pack('n', 11)];
        yield 'offset width' => [pack('n', 1).chr(5)];
        yield 'first offset' => [pack('n', 1)."\x01\x00\x01"];
        yield 'decreasing offset' => [pack('n', 2)."\x01\x01\x02\x01a"];
        yield 'span overflow' => [pack('n', 1)."\x01\x01\xff"];
    }

    public function testEveryIndexOffsetWidthAndCff2Count(): void
    {
        for ($width = 1; $width <= 4; ++$width) {
            $data = pack('N', 1).chr($width).substr(pack('N', 1), -$width).substr(pack('N', 4), -$width).'abc';
            $index = (new CffIndexReader())->read(new BinaryView($data), 0, true, new LayoutReadBudget(10));
            self::assertSame('abc', $index->objects[0]->bytes(0, 3));
            self::assertSame(strlen($data), $index->endOffset);
        }
    }

    #[DataProvider('numberEncodings')]
    public function testExactNumericEncodings(string $bytes, bool $dictionary, float $expected): void
    {
        $at = 0;
        self::assertSame($expected, (new CffNumberReader())->read(new BinaryView($bytes), $at, $dictionary));
        self::assertSame(strlen($bytes), $at);
    }

    public static function numberEncodings(): iterable
    {
        yield [chr(32), true, -107.0];
        yield ["\xfa\xff", true, 1131.0];
        yield ["\xfe\xff", true, -1131.0];
        yield ["\x1c\x80\0", true, -32768.0];
        yield ["\x1d\x80\0\0\0", true, -2147483648.0];
        yield ["\xff\xff\xff\x80\0", false, -0.5];
        yield ["\x1e\xe2\xa2\x5f", true, -2.25];
        yield ["\x1e\xff", true, 0.0];
        yield ["\x1e\xaf", true, 0.0];
    }

    public function testPredefinedCharsetsAndExpertEncoding(): void
    {
        $reader = new CffCharacterSetReader();
        $empty = new CffIndex([], 0);
        $charset = $reader->read(new BinaryView(''), 1, false, 166, $empty, new LayoutReadBudget(1000));
        self::assertSame('exclamsmall', $charset->name(new GlyphId(2)));
        self::assertSame(2, $reader->encoding(new BinaryView(''), 1, $charset, new LayoutReadBudget(1000))->glyph(33)->value);
        $custom = $reader->read(new BinaryView("xxx\x02\0\x22\0\x01"), 3, false, 3, $empty, new LayoutReadBudget(1000));
        self::assertSame([0, 34, 35], $custom->identifiers);
        $encoded = $reader->encoding(new BinaryView("xx\x81\x01\x41\x01\x01\x5a\0\x22"), 2, $custom, new LayoutReadBudget(1000));
        self::assertSame(1, $encoded->glyph(90)->value);
        self::assertSame(2, $encoded->glyph(66)->value);
    }

    public function testExplicitMatrixCompositionPreservesClosedPath(): void
    {
        $matrix = (new CffFontMatrix())->compose(new CffFontMatrix(2, 0, 0.5, 3, 10, 20));
        $path = new OutlinePath([new PathCommand(PathOperator::Move, [new OutlinePoint(20, 30)]), new PathCommand(PathOperator::Close)]);
        $actual = $matrix->transform($path, 1000);
        self::assertEqualsWithDelta(65, $actual->commands[0]->points[0]->x, 1e-9);
        self::assertEqualsWithDelta(110, $actual->commands[0]->points[0]->y, 1e-9);
    }

    public function testEmptyCff2PrivateDictionaryHasNoImplicitOffset(): void
    {
        // Header, TopDICT, empty globals, one empty charstring, one FontDICT(Private=0,0).
        $table = "\x02\0\x05\0\x05".self::numbers([14]).chr(17).self::numbers([21])."\x0c\x24".pack('N', 0);
        $table .= pack('N', 1)."\x01\x01\x01";
        $table .= pack('N', 1)."\x01\x01\x04".self::numbers([0, 0]).chr(18);
        $tables = FontFixture::tables(1, 0x4F54544F);
        $tables['CFF2'] = $table;
        $font = (new CffReader())->read((new SfntReader())->read(FontFixture::build($tables, 0x4F54544F)));
        self::assertSame([], (new CffOutlineEngine())->outline($font, new GlyphId(0))->path->commands);
    }

    public function testCff2StoreMustMatchFvarAxisCount(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitCff2.otf'));
        $tables = [];
        foreach ($font->records() as $record) {
            if ($record->tag->value !== 'fvar') {
                $tables[$record->tag->value] = $record->data()->bytes(0, $record->length);
            }
        }
        $this->expectException(MalformedFontException::class);
        (new CffReader())->read((new SfntReader())->read(FontFixture::build($tables, 0x4F54544F)));
    }

    public function testReaderRejectsOffsetsPointingIntoItsHeader(): void
    {
        $tables = FontFixture::tables(1, 0x4F54544F);
        $tables['CFF2'] = "\x02\0\x05\0\x02".self::numbers([1]).chr(17).pack('N', 0);
        $this->expectException(MalformedFontException::class);
        (new CffReader())->read((new SfntReader())->read(FontFixture::build($tables, 0x4F54544F)));
    }

    private function font(string $program, bool $cff2 = false): CffFont
    {
        $dictionary = new CffDictionary([]);
        $empty = new CffIndex([], 0);

        return new CffFont($cff2, 1000, $dictionary, new CffIndex([new BinaryView($program)], 0), $empty, [new CffFontDictionary($dictionary, $dictionary, $empty)], [0]);
    }

    /** @param list<int> $values */
    private static function numbers(array $values): string
    {
        $output = '';
        foreach ($values as $value) {
            $output .= $value >= -107 && $value <= 107 ? chr($value + 139) : chr(28).pack('n', $value & 65535);
        }

        return $output;
    }
}
