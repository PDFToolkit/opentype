<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\CoverageFormat2;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\ExtensionSubstFormat1;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat1;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat2;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Substitution\UnsupportedSubstitution;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GsubReaderTest extends TestCase
{
    public function testSingleFormatOneUsesModulo65536(): void
    {
        $subtable = self::words(1, 6, 2).self::words(1, 1, 65534);
        $table = (new GsubReader())->read(new BinaryView(self::gsub([$subtable])), 65535);
        $single = $table->lookupList->lookups[0]->subtables[0];
        self::assertInstanceOf(SingleSubstFormat1::class, $single);
        self::assertSame(0, $single->substitute(new GlyphId(65534))->value);
    }

    public function testSingleFormatTwoUsesCoverageRangeIndices(): void
    {
        $subtable = self::words(2, 12, 3, 9, 8, 7).self::words(2, 2, 1, 2, 0, 4, 4, 2);
        $table = (new GsubReader())->read(new BinaryView(self::gsub([$subtable])), 16);
        $single = $table->lookupList->lookups[0]->subtables[0];
        self::assertInstanceOf(SingleSubstFormat2::class, $single);
        self::assertInstanceOf(CoverageFormat2::class, $single->coverage);
        self::assertSame(9, $single->substitute(new GlyphId(1))->value);
        self::assertSame(8, $single->substitute(new GlyphId(2))->value);
        self::assertSame(7, $single->substitute(new GlyphId(4))->value);
        self::assertNull($single->substitute(new GlyphId(3)));
    }

    public function testExtensionOffsetsAre32BitAndRelativeToExtension(): void
    {
        $single = self::words(1, 6, 1).self::words(1, 1, 2);
        $extension = self::words(1, 1).pack('N', 65536).str_repeat("\0", 65528).$single;
        $table = (new GsubReader())->read(new BinaryView(self::gsub([$extension], 7)), 16);
        $subtable = $table->lookupList->lookups[0]->subtables[0];
        self::assertInstanceOf(ExtensionSubstFormat1::class, $subtable);
        self::assertSame(3, $subtable->extension->substitute(new GlyphId(2))->value);
    }

    #[DataProvider('validUnsupportedContexts')]
    public function testContextualRulesAreRetainedAndTheirExecutionFails(string $subtable, int $type, int $format): void
    {
        $table = (new GsubReader())->read(new BinaryView(self::gsub([$subtable], $type)), 16);
        $unsupported = $table->lookupList->lookups[0]->subtables[0];
        self::assertInstanceOf(UnsupportedSubstitution::class, $unsupported);
        self::assertSame($type, $unsupported->type);
        self::assertSame($format, $unsupported->format);
        self::assertFalse($unsupported->isSupported());
        $this->expectException(UnsupportedFontException::class);
        (new SubstitutionEngine())->apply($table, new FeatureSelection(new Tag('latn'), features: [new Tag('test')]), new GlyphSequence([new GlyphId(1)]));
    }

    public static function validUnsupportedContexts(): iterable
    {
        // Context format 1: coverage(1), one rule (1,2), nested lookup 0 at input index 1.
        yield 'context one' => [self::words(1, 8, 1, 14, 1, 1, 1, 1, 4, 2, 1, 2, 1, 0), 5, 1];
        // Context format 2: one class rule, no nonzero class assignments.
        yield 'context two' => [self::words(2, 10, 16, 1, 20, 1, 1, 1, 2, 0, 1, 4, 1, 0), 5, 2];
        // Context format 3: one input coverage and one valid lookup record.
        yield 'context three' => [self::words(3, 1, 1, 12, 0, 0, 1, 1, 1), 5, 3];
        yield 'later indices may address expanded sequence' => [self::words(3, 1, 2, 16, 0, 0, 3, 0, 1, 1, 1), 5, 3];
        // Chaining format 1: backtrack [], input [coverage], lookahead [], actions [].
        yield 'chain one' => [self::words(1, 8, 1, 14, 1, 1, 1, 1, 4, 0, 1, 0, 0), 6, 1];
        // Chaining format 2 with three empty class definitions and one class rule.
        yield 'chain two' => [self::words(2, 14, 20, 24, 28, 1, 32, 1, 1, 1, 2, 0, 2, 0, 2, 0, 1, 4, 0, 1, 0, 0), 6, 2];
        // Chaining format 3 with one input coverage.
        yield 'chain three' => [self::words(3, 0, 1, 12, 0, 0, 1, 1, 1), 6, 3];
        yield 'reverse chain' => [self::words(1, 12, 0, 0, 1, 2, 1, 1, 1), 8, 1];
    }

    public function testEmptyAlternateSetIsValidAndAllowsFollowingSubtables(): void
    {
        $empty = self::words(1, 10, 1, 8, 0).self::words(1, 1, 1);
        $choice = self::words(1, 12, 1, 8, 1, 2).self::words(1, 1, 1);
        $table = (new GsubReader())->read(new BinaryView(self::gsub([$empty, $choice], 3)), 16);
        self::assertSame([], $table->lookupList->lookups[0]->subtables[0]->alternateSets[0]->alternateGlyphIds);
        $output = (new SubstitutionEngine())->apply(
            $table,
            new FeatureSelection(new Tag('latn'), features: [new Tag('test')], alternateSelector: new \PDFToolkit\OpenType\Substitution\AlternateIndex(1)),
            new GlyphSequence([new GlyphId(1)]),
        );
        self::assertSame(2, $output->glyphs[0]->value);
    }

    public function testVersion11WithNullFeatureVariationsHasNoVariationModel(): void
    {
        $original = self::gsub([self::single()]);
        $table = self::words(1, 1, 14, 34, 48).pack('N', 0).substr($original, 10);
        $gsub = (new GsubReader())->read(new BinaryView($table), 16);
        self::assertSame(1, $gsub->minorVersion);
        self::assertNull($gsub->featureVariations);
    }

    public function testVariationRecordsAllowNullConditionAndSubstitutionOffsets(): void
    {
        $original = self::gsub([self::single()]);
        $base = self::words(1, 1, 14, 34, 48).pack('N', strlen($original) + 4).substr($original, 10);
        $table = $base.self::words(1, 0).pack('N', 1).pack('N2', 0, 0);
        $gsub = (new GsubReader())->read(new BinaryView($table), 16);
        self::assertNotNull($gsub->featureVariations);
        $this->expectException(UnsupportedFontException::class);
        (new SubstitutionEngine())->apply($gsub, new FeatureSelection(new Tag('latn'), features: [new Tag('test')]), new GlyphSequence([new GlyphId(1)]));
    }

    public function testEveryTruncationOfARealGsubTableIsRejected(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitLayout.ttf'));
        $source = $font->table(new Tag('GSUB'));
        $bytes = $source->bytes(0, $source->size());
        $reader = new GsubReader();
        for ($length = 0; $length < strlen($bytes); ++$length) {
            try {
                $reader->read(new BinaryView(substr($bytes, 0, $length)), $font->glyphCount());
                self::fail('Truncated GSUB accepted at length '.$length);
            } catch (MalformedFontException|UnsupportedFontException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAggregateBudgetAndByteLimitsAreEnforcedBeforeRecordsAreAllocated(): void
    {
        $table = self::gsub([self::single()]);
        foreach ([new ReadLimits(maxLayoutRecords: 1), new ReadLimits(maxBytes: 10)] as $limits) {
            try {
                (new GsubReader($limits))->read(new BinaryView($table), 16);
                self::fail('The configured budget was not enforced.');
            } catch (MalformedFontException) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[DataProvider('malformedSubtables')]
    public function testMalformedSubtablesAreRejected(string $subtable, int $type): void
    {
        $this->expectException(MalformedFontException::class);
        (new GsubReader())->read(new BinaryView(self::gsub([$subtable], $type)), 16);
    }

    public static function malformedSubtables(): iterable
    {
        yield 'null coverage' => [self::words(1, 0, 1), 1];
        yield 'coverage inside header' => [self::words(1, 4, 1).self::words(1, 1, 1), 1];
        yield 'coverage beyond table' => [self::words(1, 65535, 1), 1];
        yield 'unsorted coverage' => [self::words(1, 6, 1).self::words(1, 2, 2, 1), 1];
        yield 'duplicate coverage' => [self::words(1, 6, 1).self::words(1, 2, 1, 1), 1];
        yield 'source glyph outside font' => [self::words(1, 6, 0).self::words(1, 1, 16), 1];
        yield 'delta result outside font' => [self::words(1, 6, 15).self::words(1, 1, 1), 1];
        yield 'unknown coverage format' => [self::words(1, 6, 1).self::words(3, 0), 1];
        yield 'range index gap' => [self::words(1, 6, 1).self::words(2, 1, 1, 2, 1), 1];
        yield 'overlapping ranges' => [self::words(1, 6, 1).self::words(2, 2, 1, 2, 0, 2, 3, 2), 1];
        yield 'single count mismatch' => [self::words(2, 8, 1, 2).self::words(1, 2, 1, 2), 1];
        yield 'single target outside font' => [self::words(2, 8, 1, 16).self::words(1, 1, 1), 1];
        yield 'multiple empty sequence' => [self::words(1, 10, 1, 8, 0).self::words(1, 1, 1), 2];
        yield 'multiple target outside font' => [self::words(1, 12, 1, 8, 1, 16).self::words(1, 1, 1), 2];
        yield 'ligature zero components' => [self::words(1, 16, 1, 8, 1, 4, 2, 0).self::words(1, 1, 1), 4];
        yield 'self extension' => [self::words(1, 7).pack('N', 8).self::single(), 7];
        yield 'extension inside header' => [self::words(1, 1).pack('N', 4).self::single(), 7];
        yield 'unknown extension type' => [self::words(1, 9).pack('N', 8).self::single(), 7];
        yield 'context empty input' => [self::words(3, 0, 0), 5];
        yield 'context nested lookup outside list' => [self::words(3, 1, 1, 12, 0, 1, 1, 1, 1), 5];
        yield 'context input position outside input' => [self::words(3, 1, 1, 12, 1, 0, 1, 1, 1), 5];
        yield 'context coverage overlaps record header' => [self::words(3, 1, 1, 6, 0, 0, 1, 1, 1), 5];
        yield 'chain coverage overlaps remaining header' => [self::words(3, 0, 1, 8, 0, 0, 1, 1, 1), 6];
        yield 'reverse count differs from coverage' => [self::words(1, 10, 0, 0, 0, 1, 1, 1), 8];
    }

    #[DataProvider('malformedHeaders')]
    public function testMalformedHeaderOrReferencesAreRejected(int $offset, int $value): void
    {
        $bytes = self::gsub([self::single()]);
        $bytes = substr_replace($bytes, self::words($value), $offset, 2);
        $this->expectException(MalformedFontException::class);
        (new GsubReader())->read(new BinaryView($bytes), 16);
    }

    public static function malformedHeaders(): iterable
    {
        yield 'null script list' => [4, 0];
        yield 'script list points inside header' => [4, 8];
        yield 'feature list beyond table' => [6, 65535];
        yield 'script list points inside its record' => [16, 2];
        yield 'reserved lookup order' => [22, 1];
        yield 'required feature absent' => [24, 1];
        yield 'optional feature absent' => [28, 1];
        yield 'lookup absent' => [42, 1];
        yield 'lookup reserved bits' => [50, 0x20];
        yield 'lookup type unknown' => [48, 9];
        yield 'lookup has no subtables' => [52, 0];
    }

    private static function words(int ...$values): string
    {
        return pack('n*', ...$values);
    }

    private static function single(): string
    {
        return self::words(1, 6, 1).self::words(1, 1, 1);
    }

    private static function gsub(array $subtables, int $type = 1): string
    {
        $scriptList = self::words(1).'latn'.self::words(8, 4, 0, 0, 0xFFFF, 1, 0);
        $featureList = self::words(1).'test'.self::words(8, 0, 1, 0);
        $lookupHeader = 6 + 2 * count($subtables);
        $offsets = [];
        $cursor = $lookupHeader;
        foreach ($subtables as $subtable) {
            $offsets[] = $cursor;
            $cursor += strlen($subtable);
        }
        $lookup = self::words($type, 0, count($subtables), ...$offsets).implode('', $subtables);
        $lookupList = self::words(1, 4).$lookup;

        return self::words(1, 0, 10, 10 + strlen($scriptList), 10 + strlen($scriptList) + strlen($featureList)).$scriptList.$featureList.$lookupList;
    }
}
