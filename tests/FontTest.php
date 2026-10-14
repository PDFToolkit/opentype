<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ChecksumPolicy;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FontTest extends TestCase
{
    public function testReadsImmutableDirectoryAndPreservesUnknownTableBytes(): void
    {
        $tables = FontFixture::tables();
        $tables['TEST'] = 'abc';
        $font = (new SfntReader())->read(FontFixture::build($tables));
        self::assertSame(SfntVersion::TrueType, $font->version);
        self::assertSame(4, $font->glyphCount());
        self::assertCount(5, $font->records());
        self::assertSame('abc', $font->table(new Tag('TEST'))->bytes(0, 3));
        self::assertFalse($font->hasTable(new Tag('GSUB')));
        self::assertTrue($font->hasTable(new Tag('head')));
        self::assertSame(3, $font->record(new Tag('TEST'))->length);
    }

    public function testCffDirectoryIsSupportedWithoutClaimingOutlineInterpretation(): void
    {
        $font = (new SfntReader())->read(FontFixture::build(FontFixture::tables(4, 0x4F54544F), 0x4F54544F));
        self::assertSame(SfntVersion::Cff, $font->version);
        self::assertSame(4, $font->glyphCount());
    }

    /** @return iterable<string, array{string}> */
    public static function badSignatures(): iterable
    {
        foreach (['wOFF', 'wOF2', 'ttcf', 'true', 'typ1'] as $signature) {
            yield $signature => [$signature];
        }
    }

    #[DataProvider('badSignatures')]
    public function testRejectsUnsupportedContainers(string $signature): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new SfntReader())->read($signature.str_repeat("\0", 20));
    }

    public function testTruncatedDirectoryIsRejected(): void
    {
        $this->expectException(MalformedFontException::class);
        (new SfntReader())->read(substr(FontFixture::build(FontFixture::tables()), 0, 20));
    }

    public function testDuplicateTagsAreRejectedEvenWithChecksumsDisabled(): void
    {
        $font = FontFixture::build(FontFixture::tables());
        $font = substr_replace($font, 'head', 28, 4);
        $this->expectException(MalformedFontException::class);
        (new SfntReader(checksumPolicy: ChecksumPolicy::Ignore))->read($font);
    }

    public function testOverlappingTablesAreRejectedEvenWithChecksumsDisabled(): void
    {
        $font = FontFixture::build(FontFixture::tables());
        $font = substr_replace($font, substr($font, 20, 4), 36, 4);
        $this->expectException(MalformedFontException::class);
        (new SfntReader(checksumPolicy: ChecksumPolicy::Ignore))->read($font);
    }

    public function testInvalidTableChecksumIsRejected(): void
    {
        $font = FontFixture::build(FontFixture::tables());
        $font[76] = "\xFF";
        $this->expectException(MalformedFontException::class);
        (new SfntReader())->read($font);
    }

    public function testWholeFontAdjustmentPolicyIsExplicit(): void
    {
        $font = FontFixture::build(FontFixture::tables());
        $font = substr_replace($font, "\0\0\0\0", 84, 4);
        self::assertSame(4, (new SfntReader(checksumPolicy: ChecksumPolicy::TableChecksums))->read($font)->glyphCount());
        $this->expectException(MalformedFontException::class);
        (new SfntReader())->read($font);
    }

    public function testAttackerControlledSearchFieldsAreNotTrusted(): void
    {
        $font = substr_replace(FontFixture::build(FontFixture::tables()), "\xFF\xFF", 6, 2);
        $this->expectException(MalformedFontException::class);
        (new SfntReader())->read($font);
    }

    public function testReadBudgetIsAppliedBeforeDirectoryAllocation(): void
    {
        $this->expectException(MalformedFontException::class);
        (new SfntReader(new ReadLimits(maxTables: 2)))->read(FontFixture::build(FontFixture::tables()));
    }

    public function testGlyphCountBudgetIsApplied(): void
    {
        $this->expectException(MalformedFontException::class);
        (new SfntReader(new ReadLimits(maxGlyphs: 3)))->read(FontFixture::build(FontFixture::tables()));
    }

    public function testByteBudgetIsApplied(): void
    {
        $this->expectException(MalformedFontException::class);
        (new SfntReader(new ReadLimits(maxBytes: 12)))->read(FontFixture::build(FontFixture::tables()));
    }

    public function testReadLimitsRejectUnboundedConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReadLimits(maxLayoutRecords: PHP_INT_MAX);
    }
}
