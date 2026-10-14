<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\CodePoint;
use PHPUnit\Framework\TestCase;

final class CmapTest extends TestCase
{
    public function testFormatFourRangeOffsetsApplyDeltaOnlyToNonzeroGlyphs(): void
    {
        $map = (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 1, $this->formatFour()]])), 10);
        self::assertSame(4, $map->format());
        self::assertSame(6, $map->glyphFor(new CodePoint(65))->value);
        self::assertSame(0, $map->glyphFor(new CodePoint(66))->value);
        self::assertSame(7, $map->glyphFor(new CodePoint(67))->value);
        self::assertSame(0, $map->glyphFor(new CodePoint(68))->value);
        self::assertSame(0, $map->glyphFor(new CodePoint(0x1F600))->value);
    }

    public function testPrefersFullRepertoireAndDoesNotMergeExclusiveSubtables(): void
    {
        $twelve = $this->formatTwelve([[65, 66, 1], [0x1F600, 0x1F600, 9]]);
        $map = (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 1, $this->formatFour()], [3, 10, $twelve]])), 10);
        self::assertSame(12, $map->format());
        self::assertSame(1, $map->glyphFor(new CodePoint(65))->value);
        self::assertSame(0, $map->glyphFor(new CodePoint(67))->value);
        self::assertSame(9, $map->glyphFor(new CodePoint(0x1F600))->value);
    }

    public function testVariationSequencesDistinguishDefaultOverrideAndUnsupported(): void
    {
        $map = (new UnicodeCmapReader())->read(new BinaryView($this->cmap([
            [0, 5, $this->formatFourteen()],
            [3, 10, $this->formatTwelve([[65, 67, 1]])],
        ])), 10);
        $selector = new CodePoint(0xFE0F);
        self::assertSame(1, $map->glyphForVariation(new CodePoint(65), $selector)?->value);
        self::assertSame(7, $map->glyphForVariation(new CodePoint(66), $selector)?->value);
        self::assertNull($map->glyphForVariation(new CodePoint(67), $selector));
        self::assertNull($map->glyphForVariation(new CodePoint(65), new CodePoint(0xFE0E)));
    }

    public function testUntrustedFormatTwelveGroupCountCannotAllocate(): void
    {
        $subtable = pack('nnNNN', 12, 0, 16, 0, 0xFFFFFFFF);
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 10, $subtable]])), 10);
    }

    public function testReferencesOutsideGlyphCountAreRejected(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 10, $this->formatTwelve([[65, 66, 9]])]])), 10);
    }

    public function testSurrogateRangeIsRejected(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 10, $this->formatTwelve([[0xD800, 0xD800, 1]])]])), 10);
    }

    public function testOverlappingSequentialGroupsAreRejected(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 10, $this->formatTwelve([[65, 66, 1], [66, 67, 3]])]])), 10);
    }

    public function testMalformedFormatFourRangeOffsetIsRejected(): void
    {
        $subtable = substr_replace($this->formatFour(), pack('n', 65534), 28, 2);
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 1, $subtable]])), 10);
    }

    public function testEncodingRecordCannotAddressItsOwnDirectory(): void
    {
        $bytes = substr_replace($this->cmap([[3, 1, $this->formatFour()]]), pack('N', 4), 8, 4);
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($bytes), 10);
    }

    public function testInvalidVariationSelectorIsRejected(): void
    {
        $subtable = substr_replace($this->formatFourteen(), "\0\0A", 10, 3);
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([
            [0, 5, $subtable], [3, 10, $this->formatTwelve([[65, 67, 1]])],
        ])), 10);
    }

    public function testVariationRecordOffsetCannotAddressSelectorDirectory(): void
    {
        $subtable = substr_replace($this->formatFourteen(), pack('N', 10), 13, 4);
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([
            [0, 5, $subtable], [3, 10, $this->formatTwelve([[65, 67, 1]])],
        ])), 10);
    }

    public function testExplicitRecordBudgetBoundsParsing(): void
    {
        $this->expectException(MalformedFontException::class);
        (new UnicodeCmapReader(new ReadLimits(maxLayoutRecords: 1)))->read(new BinaryView($this->cmap([[3, 1, $this->formatFour()]])), 10);
    }

    public function testSymbolCmapIsNotSilentlyTreatedAsUnicode(): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new UnicodeCmapReader())->read(new BinaryView($this->cmap([[3, 0, $this->formatFour()]])), 10);
    }

    /** @param list<array{int, int, string}> $records */
    private function cmap(array $records): string
    {
        $directory = pack('nn', 0, count($records));
        $body = '';
        $offset = 4 + 8 * count($records);
        foreach ($records as [$platform, $encoding, $subtable]) {
            $directory .= pack('nnN', $platform, $encoding, $offset);
            $body .= $subtable;
            $offset += strlen($subtable);
        }

        return $directory.$body;
    }

    private function formatFour(): string
    {
        return pack('nnnnnnn', 4, 38, 0, 4, 4, 1, 0)
            .pack('nnn', 67, 65535, 0)
            .pack('nn', 65, 65535)
            .pack('nn', 1, 1)
            .pack('nn', 4, 0)
            .pack('nnn', 5, 0, 6);
    }

    /** @param list<array{int, int, int}> $groups */
    private function formatTwelve(array $groups): string
    {
        $table = pack('nnNNN', 12, 0, 16 + 12 * count($groups), 0, count($groups));
        foreach ($groups as [$start, $end, $glyph]) {
            $table .= pack('NNN', $start, $end, $glyph);
        }

        return $table;
    }

    private function formatFourteen(): string
    {
        return pack('nNN', 14, 38, 1)."\0\xFE\x0F".pack('NN', 21, 29)
            .pack('N', 1)."\0\0A\0"
            .pack('N', 1)."\0\0B".pack('n', 7);
    }
}
