<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Metrics\EmbeddingPermission;
use PDFToolkit\OpenType\Metrics\EmbeddingRights;
use PDFToolkit\OpenType\Metrics\Os2Reader;
use PDFToolkit\OpenType\Names\MacintoshRomanNameDecoder;
use PDFToolkit\OpenType\Names\NameReader;
use PDFToolkit\OpenType\Names\NameRecord;
use PDFToolkit\OpenType\Names\UnicodeNameDecoder;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    public function testOriginalFontNamesRemainExplicitlySelectableAndDecoded(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAxes.ttf'));
        $names = (new NameReader())->read($font->table(new Tag('name')));
        self::assertSame('Weight', $names->select(256, 3, 1, 0x409)[0]->text(new UnicodeNameDecoder()));
        self::assertSame([], $names->select(256, 3, 1, 0x40C));
        self::assertSame('éÄ', (new NameRecord(1, 0, 0, 1, "\x8e\x80"))->text(new MacintoshRomanNameDecoder()));
        self::assertSame('A😀', (new NameRecord(3, 10, 0x409, 1, pack('n*', 65, 0xD83D, 0xDE00)))->text(new UnicodeNameDecoder()));
    }

    public function testNameOneLanguageTagsAndUnknownLanguagesArePreservedWithoutFallback(): void
    {
        $bytes = pack('n*', 1, 2, 36).pack('n*', 0, 4, 0x8000, 1, 2, 0).pack('n*', 0, 4, 0x8001, 1, 2, 0).pack('n*', 1, 10, 2).pack('n*', 65, 102, 114, 45, 67, 65);
        $table = (new NameReader())->read(new BinaryView($bytes));
        self::assertSame(['fr-CA'], $table->languageTags);
        self::assertSame('fr-CA', $table->records[0]->languageTag);
        self::assertTrue($table->records[0]->usableLanguage);
        self::assertFalse($table->records[1]->usableLanguage);
        self::assertSame([], $table->select(1, 0, 4, 0x8001));
    }

    public function testAliasedNameStringsConsumeAggregateBudget(): void
    {
        $bytes = pack('n*', 0, 2, 30).pack('n*', 3, 1, 0x409, 1, 40, 0).pack('n*', 3, 1, 0x409, 2, 40, 0).str_repeat("\0A", 20);
        $this->expectException(MalformedFontException::class);
        (new NameReader(new ReadLimits(maxBytes: 75)))->read(new BinaryView($bytes));
    }

    #[DataProvider('invalidNames')]
    public function testNameOffsetsSortingAndUtf16AreBounded(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new NameReader())->read(new BinaryView($bytes));
    }

    public static function invalidNames(): iterable
    {
        yield 'storage overlaps record' => [pack('n*', 0, 1, 6, 3, 1, 0x409, 1, 2, 0)];
        yield 'string exceeds storage' => [pack('n*', 0, 1, 18, 3, 1, 0x409, 1, 2, 65535)];
        yield 'version0 language tag' => [pack('n*', 0, 1, 18, 3, 1, 0x8000, 1, 2, 0, 65)];
        yield 'unsorted records' => [pack('n*', 0, 2, 30, 3, 1, 0x409, 2, 0, 0, 3, 1, 0x409, 1, 0, 0)];
        yield 'odd language bytes' => [pack('n*', 1, 0, 12, 1, 1, 0).'x'];
    }

    #[DataProvider('invalidUtf16')]
    public function testMalformedNamesAreNeverSilentlyRepaired(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new NameRecord(3, 1, 0x409, 1, $bytes))->text(new UnicodeNameDecoder());
    }

    public static function invalidUtf16(): iterable
    {
        yield ['x'];
        yield [pack('n', 0xD800)];
        yield [pack('n*', 0xD800, 65)];
        yield [pack('n', 0xDC00)];
    }

    public function testLegacyNameEncodingNeedsAnExplicitDecoder(): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new NameRecord(1, 1, 0, 1, 'name'))->text(new UnicodeNameDecoder());
    }

    #[DataProvider('os2Versions')]
    public function testAllOs2VersionsExposePresentFieldsAndAbsence(int $version, int $length): void
    {
        $bytes = self::os2($version, $length);
        $table = (new Os2Reader())->read(new BinaryView($bytes));
        self::assertSame($version, $table->version);
        self::assertSame(-123, $table->xAvgCharWidth);
        self::assertSame(700, $table->usWeightClass);
        self::assertSame(5, $table->usWidthClass);
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $table->panose->values);
        self::assertSame('TEST', $table->achVendId);
        self::assertSame($length === 68 ? null : 800, $table->sTypoAscender);
        self::assertSame($version >= 2 ? 500 : null, $table->sxHeight);
        self::assertSame($version >= 1, $table->codePageRanges !== null);
        self::assertSame($version >= 4, $table->useTypographicMetrics());
        self::assertTrue($table->unicodeRanges->containsBit(31));
        if ($version === 5) {
            self::assertTrue($table->opticalPointRange()->contains(12));
            self::assertFalse($table->opticalPointRange()->contains(24));
        }
        // Extra bytes are ignored according to the declared version.
        self::assertEquals($table, (new Os2Reader())->read(new BinaryView($length === 68 ? $bytes : $bytes.'extra')));
    }

    public static function os2Versions(): iterable
    {
        yield [0, 68];
        yield [0, 78];
        yield [1, 86];
        yield [2, 96];
        yield [3, 96];
        yield [4, 96];
        yield [5, 100];
    }

    public function testEmbeddingPermissionsRespectVersionSpecificFlags(): void
    {
        self::assertFalse((new EmbeddingRights(0x0304, 1))->noSubsetting());
        self::assertFalse((new EmbeddingRights(0x0304, 1))->bitmapOnly());
        self::assertTrue((new EmbeddingRights(0x0304, 2))->noSubsetting());
        self::assertTrue((new EmbeddingRights(0x0304, 2))->bitmapOnly());
        self::assertSame(EmbeddingPermission::Editable, (new EmbeddingRights(12, 2))->permission());
        self::assertSame(EmbeddingPermission::Restricted, (new EmbeddingRights(2, 5))->permission());
        $this->expectException(\InvalidArgumentException::class);
        new EmbeddingRights(12, 3);
    }

    public function testOs2MissingVersionFieldsAreRejected(): void
    {
        $this->expectException(MalformedFontException::class);
        (new Os2Reader())->read(new BinaryView(substr(self::os2(5, 100), 0, 99)));
    }

    private static function os2(int $version, int $length): string
    {
        $bytes = pack('n*', $version, -123, 700, 5, 4).str_repeat("\0", 22).pack('C*', 0, 1, 2, 3, 4, 5, 6, 7, 8, 9).pack('N*', 0x80000000, 0, 0, 0).'TEST'.pack('n*', 0x80, 32, 255, 800, -200, 10, 900, 300).pack('N*', 1, 0).pack('n*', 500, 700, 0, 32, 3, 240, 480);

        return substr($bytes, 0, $length);
    }
}
