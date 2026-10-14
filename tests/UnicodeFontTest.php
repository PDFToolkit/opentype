<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnicodeFontTest extends TestCase
{
    /** @return iterable<array{string, SfntVersion}> */
    public static function outlineContainers(): iterable
    {
        yield ['ToolkitUnicode.ttf', SfntVersion::TrueType];
        yield ['ToolkitUnicode.otf', SfntVersion::Cff];
    }

    #[DataProvider('outlineContainers')]
    public function testIndependentCompiledFontSupportsSupplementaryUnicodeAndUvs(string $filename, SfntVersion $version): void
    {
        $bytes = file_get_contents(__DIR__.'/fixtures/'.$filename);
        self::assertIsString($bytes);
        $font = (new SfntReader())->read($bytes);
        self::assertSame($version, $font->version);
        self::assertSame(5, $font->glyphCount());
        $map = (new UnicodeCmapReader())->read($font->table(new Tag('cmap')), $font->glyphCount());
        self::assertSame(12, $map->format());
        self::assertSame(4, $map->glyphFor(new CodePoint(0x1F600))->value);
        self::assertSame(1, $map->glyphForVariation(new CodePoint(65), new CodePoint(0xFE0F))?->value);
        self::assertSame(3, $map->glyphForVariation(new CodePoint(66), new CodePoint(0xFE0F))?->value);
        self::assertSame(3, $map->glyphForVariation(new CodePoint(0x1F600), new CodePoint(0xE0100))?->value);
        self::assertSame(0, $map->glyphFor(new CodePoint(0x1820))->value);
        self::assertSame(3, $map->glyphForVariation(new CodePoint(0x1820), new CodePoint(0x180B))?->value);
        self::assertNull($map->glyphForVariation(new CodePoint(65), new CodePoint(0xE0100)));
        $metrics = (new HorizontalMetricsReader())->read($font);
        self::assertSame(1000, $metrics->unitsPerEm);
        self::assertSame(700, $metrics->advanceWidth(new GlyphId(4)));
        self::assertSame(-20, $metrics->leftSideBearing(new GlyphId(0)));
        self::assertSame(20, $metrics->leftSideBearing(new GlyphId(4)));
    }

    public function testEveryTruncatedPrefixIsRejectedAsMalformed(): void
    {
        $bytes = file_get_contents(__DIR__.'/fixtures/ToolkitUnicode.ttf');
        self::assertIsString($bytes);
        $reader = new SfntReader();
        for ($length = 0; $length < strlen($bytes); ++$length) {
            try {
                $reader->read(substr($bytes, 0, $length));
                self::fail('Truncated SFNT prefix was accepted at length '.$length);
            } catch (\PDFToolkit\OpenType\Exception\MalformedFontException) {
                // Every prefix loses required table bytes, directory data, or the checksum adjustment.
            }
        }
        self::assertGreaterThan(1000, strlen($bytes));
    }
}
