<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\CodePoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdditionalCmapTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function formats(): iterable
    {
        foreach ([0, 2, 6, 8, 10, 13] as $format) {
            yield 'format '.$format => [$format];
        }
    }

    #[DataProvider('formats')]
    public function testMappingsAndMissingCharacters(int $format): void
    {
        $fixture = new AdditionalCmapFixture();
        $map = (new UnicodeCmapReader())->read(new BinaryView($fixture->cmap($format)), 5);
        self::assertSame($format, $map->format());
        foreach ($fixture->probes($format) as $code => $expected) {
            self::assertSame($expected, $map->glyphFor(new CodePoint($code))->value, 'U+'.dechex($code));
        }
    }

    #[DataProvider('formats')]
    public function testTruncatedSubtableIsRejected(int $format): void
    {
        $fixture = new AdditionalCmapFixture();
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($fixture->cmap($format, substr($fixture->subtable($format), 0, -1))), 5);
    }

    #[DataProvider('formats')]
    public function testGlyphReferencesAreBoundedByMaxp(int $format): void
    {
        $fixture = new AdditionalCmapFixture();
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($fixture->cmap($format)), 3);
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function malformedFields(): iterable
    {
        yield 'zero length' => [0, 2, pack('n', 0)];
        yield 'key not multiple of eight' => [2, 6, pack('n', 1)];
        yield 'subheader range outside byte' => [2, 518, pack('nn', 255, 3)];
        yield 'subheader overlap' => [2, 524, pack('n', 2)];
        yield 'subheader offset outside table' => [2, 524, pack('n', 65534)];
        yield 'odd subheader offset' => [2, 524, pack('n', 11)];
        yield 'format 6 overflow' => [6, 6, pack('n', 65535)];
        yield 'format 6 surrogate mapping' => [6, 6, pack('n', 0xD800)];
        yield 'format 8 reserved' => [8, 2, pack('n', 1)];
        yield 'format 8 missing high bit' => [8, 12, "\0"];
        yield 'format 8 ambiguous standalone code' => [8, 12 + 8, "\x40"];
        yield 'format 8 overlapping groups' => [8, 8220, pack('NN', 66, 67)];
        yield 'format 10 reserved' => [10, 2, pack('n', 1)];
        yield 'format 10 scalar overflow' => [10, 12, pack('N', 0x10FFFF)];
        yield 'format 10 hostile count' => [10, 16, pack('N', 0xFFFFFFFF)];
        yield 'format 13 descending group' => [13, 16, pack('NN', 66, 65)];
        yield 'format 13 scalar overflow' => [13, 20, pack('N', 0x110000)];
        yield 'format 13 hostile count' => [13, 12, pack('N', 0xFFFFFFFF)];
    }

    #[DataProvider('malformedFields')]
    public function testMalformedFieldsAreRejected(int $format, int $position, string $replacement): void
    {
        $fixture = new AdditionalCmapFixture();
        $bytes = substr_replace($fixture->subtable($format), $replacement, $position, strlen($replacement));
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($fixture->cmap($format, $bytes)), 5);
    }

    public function testCompactLastResortRangeDoesNotConsumePerCharacterRecords(): void
    {
        $map = (new UnicodeCmapReader(new ReadLimits(maxLayoutRecords: 2)))
            ->read(new BinaryView((new AdditionalCmapFixture())->cmap(13)), 5);
        self::assertSame(3, $map->glyphFor(new CodePoint(0x10FFFF))->value);
    }

    public function testArrayEntriesConsumeBudgetBeforeAllocation(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader(new ReadLimits(maxLayoutRecords: 256)))
            ->read(new BinaryView((new AdditionalCmapFixture())->cmap(0)), 5);
    }

    public function testMacintoshEncodingIsNotReinterpretedAsUnicode(): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView((new AdditionalCmapFixture())->cmap(2, platform: 1)), 5);
    }

    public function testFormatThirteenRequiresItsDedicatedEncoding(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView((new AdditionalCmapFixture())->cmap(13, encoding: 4)), 5);
    }

    public function testWindowsEncodingStillRequiresPrescribedFormat(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView((new AdditionalCmapFixture())->cmap(10, encoding: 10, platform: 3)), 5);
    }

    public function testDefaultUvsCanUseAnAdditionalFormat(): void
    {
        $fixture = new AdditionalCmapFixture();
        $ten = $fixture->subtable(10);
        $uvs = pack('nNN', 14, 29, 1)."\0\xFE\x0F".pack('NN', 21, 0).pack('N', 1)."\1\0\0\0";
        $cmap = pack('nnnnNnnN', 0, 2, 0, 4, 20, 0, 5, 20 + strlen($ten)).$ten.$uvs;
        $map = (new UnicodeCmapReader())->read(new BinaryView($cmap), 5);
        self::assertSame(1, $map->glyphForVariation(new CodePoint(0x10000), new CodePoint(0xFE0F))?->value);
        self::assertNull($map->glyphForVariation(new CodePoint(0x10001), new CodePoint(0xFE0F)));
    }

    public function testFullRepertoireIsSelectedExclusively(): void
    {
        $fixture = new AdditionalCmapFixture();
        $six = $fixture->subtable(6);
        $ten = $fixture->subtable(10);
        $bytes = pack('nnnnNnnN', 0, 2, 0, 3, 20, 0, 4, 20 + strlen($six)).$six.$ten;
        $map = (new UnicodeCmapReader())->read(new BinaryView($bytes), 5);
        self::assertSame(10, $map->format());
        self::assertSame(0, $map->glyphFor(new CodePoint(65))->value);
        self::assertSame(1, $map->glyphFor(new CodePoint(0x10000))->value);
    }
}
