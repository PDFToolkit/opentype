<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BinaryViewTest extends TestCase
{
    public function testBigEndianFieldsAndRelativeSlices(): void
    {
        $bytes = new BinaryView("\xAA\x80\x00\xFF\xFF\xFF\xFF\xFE");
        self::assertSame(170, $bytes->uint8(0));
        self::assertSame(32768, $bytes->uint16(1));
        self::assertSame(-32768, $bytes->int16(1));
        self::assertSame(0xFFFFFE, $bytes->uint24(5));
        self::assertSame(0xFFFFFFFF, $bytes->uint32(3));
        $slice = $bytes->view(1, 6)->view(2, 4);
        self::assertSame(0xFFFFFFFF, $slice->uint32(0));
        self::assertSame('', $slice->bytes(4, 0));
        self::assertSame(0, $slice->view(4)->size());
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidRanges(): iterable
    {
        yield 'negative offset' => [-1, 1];
        yield 'negative length' => [0, -1];
        yield 'past end' => [5, 0];
        yield 'crosses end' => [3, 2];
        yield 'overflow offset' => [PHP_INT_MAX, 1];
        yield 'overflow length' => [1, PHP_INT_MAX];
    }

    #[DataProvider('invalidRanges')]
    public function testRangesAreBoundedWithoutOverflow(int $offset, int $length): void
    {
        $this->expectException(MalformedFontException::class);
        (new BinaryView('abcd'))->view($offset, $length);
    }

    public function testChildCannotReadPastItsOwnBoundary(): void
    {
        $this->expectException(MalformedFontException::class);
        (new BinaryView('abcdef'))->view(1, 2)->uint16(1);
    }

    public function testManyViewsShareTheirBackingBytes(): void
    {
        $source = new BinaryView(str_repeat('x', 4 * 1024 * 1024));
        $before = memory_get_usage();
        $views = [];
        for ($i = 0; $i < 1024; ++$i) {
            $views[] = $source->view($i);
        }
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before);
        self::assertSame(4 * 1024 * 1024 - 1023, $views[1023]->size());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTags(): iterable
    {
        foreach (['', 'abc', 'abcde', '    ', ' a  ', 'a b ', "a\0bc", "abc\x7F", 'éab'] as $tag) {
            yield bin2hex($tag) => [$tag];
        }
    }

    #[DataProvider('invalidTags')]
    public function testTagFollowsNormativeAsciiPadding(string $tag): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Tag($tag);
    }

    public function testValueObjectsPreserveNormativeValues(): void
    {
        self::assertSame('CFF ', (new Tag('CFF '))->value);
        self::assertSame('cvt ', (new Tag('cvt '))->value);
        self::assertSame(65535, (new GlyphId(65535))->value);
        self::assertSame(0x10FFFF, (new CodePoint(0x10FFFF))->value);
    }

    /** @return iterable<array{int}> */
    public static function invalidCodePoints(): iterable
    {
        foreach ([-1, 0xD800, 0xDFFF, 0x110000] as $code) {
            yield [$code];
        }
    }

    #[DataProvider('invalidCodePoints')]
    public function testSurrogatesAndOutOfRangeCodePointsAreRejected(int $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CodePoint($code);
    }
}
