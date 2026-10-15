<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Metadata\FontHeaderReader;
use PDFToolkit\OpenType\Metadata\GaspReader;
use PDFToolkit\OpenType\Metadata\MaximumProfileReader;
use PDFToolkit\OpenType\Metadata\PostScriptReader;
use PDFToolkit\OpenType\Metadata\StyleAttributesReader;
use PDFToolkit\OpenType\Metadata\StyleValueFormat;
use PDFToolkit\OpenType\Metrics\HorizontalHeaderReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FontHeadersTest extends TestCase
{
    public function testCompleteMetadataAndStylesFromOriginalFixture(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMetadata.ttf'));
        $head = (new FontHeaderReader())->read($font->table(new Tag('head')));
        self::assertSame(1.25, $head->fontRevision);
        self::assertSame(3850070400, $head->created);
        self::assertSame(700, $head->bounds->yMax);
        $hhea = (new HorizontalHeaderReader())->read($font->table(new Tag('hhea')));
        self::assertSame(-200, $hhea->descender);
        self::assertSame(600, $hhea->advanceWidthMax);
        $maxp = (new MaximumProfileReader())->read($font->table(new Tag('maxp')));
        self::assertSame(3, $maxp->maxPoints);
        self::assertSame(0, $maxp->maxSizeOfInstructions);
        $post = (new PostScriptReader())->read($font->table(new Tag('post')), $font->glyphCount());
        self::assertSame(-12.5, $post->italicAngle);
        self::assertSame('a.alt', $post->glyphName(new GlyphId(6)));
        $gasp = (new GaspReader())->read($font->table(new Tag('gasp')));
        self::assertTrue($gasp->atPpem(8)->grayscale());
        self::assertFalse($gasp->atPpem(8)->gridFit());
        self::assertTrue($gasp->atPpem(9)->symmetricGridFit());
        self::assertTrue($gasp->atPpem(65535)->symmetricSmoothing());
        $stat = (new StyleAttributesReader())->read($font->table(new Tag('STAT')), (new FvarReader())->read($font->table(new Tag('fvar'))));
        self::assertSame(['wdth', 'wght'], array_map(fn ($a) => $a->tag->value, $stat->axes));
        self::assertSame([StyleValueFormat::Combination, StyleValueFormat::Range, StyleValueFormat::Point], array_map(fn ($v) => $v->format, $stat->matching(new UserCoordinates(['wght' => 700, 'wdth' => 81.25]))));
        $regular = $stat->matching(new UserCoordinates(['wght' => 400, 'wdth' => 100]));
        self::assertCount(2, $regular);
        self::assertTrue($regular[0]->elidable());
        self::assertSame(700.0, $regular[1]->linkedValue);
        self::assertSame([], $stat->matching(new UserCoordinates()));
    }

    public function testSignedLongDateTimePreservesAll64Bits(): void
    {
        $bytes = FontFixture::tables()['head'];
        $bytes = substr_replace($bytes, pack('NNNN', 0x80000000, 0, 0xFFFFFFFF, 0xFFFFFFFF), 20, 16);
        $head = (new FontHeaderReader())->read(new BinaryView($bytes));
        self::assertSame(PHP_INT_MIN, $head->created);
        self::assertSame(-1, $head->modified);
    }

    public function testMaxpCffVersionHasNoInventedTrueTypeLimits(): void
    {
        $profile = (new MaximumProfileReader())->read(new BinaryView(pack('Nn', 0x5000, 4)));
        self::assertSame(4, $profile->numGlyphs);
        self::assertNull($profile->maxPoints);
        self::assertNull($profile->maxComponentDepth);
    }

    public function testPostStandardDeprecatedAndAbsentNames(): void
    {
        $header = pack('N', 0x10000).str_repeat("\0", 28);
        self::assertSame('A', (new PostScriptReader())->read(new BinaryView($header), 258)->glyphName(new GlyphId(36)));
        $deprecated = pack('N', 0x25000).str_repeat("\0", 28).pack('n', 3)."\x24\x24\x24";
        self::assertSame(['A', 'B', 'C'], (new PostScriptReader())->read(new BinaryView($deprecated), 3)->glyphNames);
        $missing = pack('N', 0x30000).str_repeat("\0", 28);
        self::assertNull((new PostScriptReader())->read(new BinaryView($missing), 1)->glyphName(new GlyphId(0)));
    }

    #[DataProvider('badPostNames')]
    public function testInvalidPostNamesCannotEscapePascalBoundaries(string $name): void
    {
        $table = pack('N', 0x20000).str_repeat("\0", 28).pack('nn', 1, 258).$name;
        $this->expectException(MalformedFontException::class);
        (new PostScriptReader())->read(new BinaryView($table), 1);
    }

    public static function badPostNames(): iterable
    {
        yield 'truncated' => ["\x06abc"];
        yield 'long' => ["\x40".str_repeat('a', 64)];
        yield 'space' => ["\x03a b"];
        yield 'non ascii' => ["\x02\xc3\xa9"];
    }

    #[DataProvider('badGasp')]
    public function testGaspRangesRejectMalformedOrderingAndVersionFlags(string $table): void
    {
        $this->expectException(MalformedFontException::class);
        (new GaspReader())->read(new BinaryView($table));
    }

    public static function badGasp(): iterable
    {
        yield 'reserved bits' => [pack('n*', 1, 1, 65535, 16)];
        yield 'version0 flags' => [pack('n*', 0, 1, 65535, 4)];
        yield 'duplicates' => [pack('n*', 1, 2, 65535, 3, 65535, 3)];
        yield 'truncated' => [pack('n*', 1, 2, 65535, 3)];
    }

    public function testStatAllowsExtendedAxisRecordSizeAndUsesRelativeValueOffsets(): void
    {
        $table = pack('nnnnNnNn', 1, 1, 12, 1, 20, 1, 32, 2).'wght'.pack('nnN', 256, 7, 0).pack('n', 2).pack('nnnnN', 1, 0, 2, 2, 400 * 65536);
        $stat = (new StyleAttributesReader())->read(new BinaryView($table));
        self::assertSame(7, $stat->axes[0]->ordering);
        self::assertSame(400.0, $stat->values[0]->coordinates[0]->value);
    }

    #[DataProvider('badStat')]
    public function testStatReferencesAndBoundsAreValidated(string $table): void
    {
        $this->expectException(MalformedFontException::class);
        (new StyleAttributesReader())->read(new BinaryView($table));
    }

    public static function badStat(): iterable
    {
        yield 'axis in header' => [pack('nnnnNnNn', 1, 1, 8, 1, 2, 0, 0, 2)];
        yield 'value without axes' => [pack('nnnnNnNn', 1, 1, 8, 0, 0, 1, 20, 2).pack('n', 2)];
        yield 'duplicate axes' => [pack('nnnnNnNn', 1, 1, 8, 2, 20, 0, 0, 2).'wght'.pack('nn', 256, 0).'wght'.pack('nn', 257, 1)];
        yield 'value points in offset array' => [pack('nnnnNnNn', 1, 1, 8, 1, 20, 1, 28, 2).'wght'.pack('nnn', 256, 0, 0)];
        yield 'absent value axis' => [pack('nnnnNnNn', 1, 1, 8, 1, 20, 1, 28, 2).'wght'.pack('nnn', 256, 0, 2).pack('nnnnN', 1, 1, 0, 256, 400 * 65536)];
    }
}
