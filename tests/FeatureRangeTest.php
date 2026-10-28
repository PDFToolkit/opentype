<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\LookupList;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureRange;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\GsubTable;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat1;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureRangeTest extends TestCase
{
    private function fixture(string $name = 'ToolkitLayout.ttf'): GsubTable
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/'.$name));
        return (new GsubReader())->read($font->table(new Tag('GSUB')), $font->glyphCount());
    }

    /** @param list<int> $ids */
    private function sequence(array $ids, ?array $clusters = null): GlyphSequence
    {
        return new GlyphSequence(array_map(static fn (int $id): GlyphId => new GlyphId($id), $ids), $clusters);
    }

    /** @return list<int> */
    private function ids(GlyphSequence $sequence): array
    {
        return array_map(static fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs);
    }

    public function testLigatureDoesNotConsumeAnUnselectedInput(): void
    {
        $table = $this->fixture();
        $input = $this->sequence([4, 5, 4, 5]);
        $engine = new SubstitutionEngine();
        $selected = $engine->apply($table, new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('liga'), 2, 4)]), $input);
        self::assertSame([4, 5, 6], $this->ids($selected));
        self::assertSame([0, 1, 2], $selected->featureClusters);
        self::assertSame([[0], [1], [2, 3]], $selected->componentClusters);
        $split = $engine->apply($table, new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('liga'), 0, 1)]), $input);
        self::assertSame([4, 5, 4, 5], $this->ids($split));
    }

    public function testContextOutsideSelectionRemainsVisible(): void
    {
        $input = $this->sequence([4, 2, 3, 5, 4, 2, 3, 5]);
        $selected = (new SubstitutionEngine())->apply($this->fixture('ToolkitAdvanced.ttf'), new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('s103'), 1, 3)]), $input);
        self::assertSame([4, 6, 3, 5, 4, 2, 3, 5], $this->ids($selected));
    }

    public function testExpansionRetainsRangeIdentityForFollowingStages(): void
    {
        $engine = new SubstitutionEngine();
        $table = $this->fixture();
        $input = $this->sequence([6, 6], [10, 20]);
        $expanded = $engine->apply($table, new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('ccmp'), 20, 21)]), $input);
        self::assertSame([6, 4, 5], $this->ids($expanded));
        self::assertSame([10, 20, 20], $expanded->featureClusters);
        $restored = $engine->apply($table, new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('liga'), 20, 21)]), $expanded);
        self::assertSame([6, 6], $this->ids($restored));
        self::assertSame([10, 20], $restored->featureClusters);
    }

    public function testLaterOverridesDisableAndReenableOneGlobalFeature(): void
    {
        $selection = new FeatureSelection(new Tag('latn'), features: [new Tag('liga')], ranges: [new FeatureRange(new Tag('liga'), 0, 4, false), new FeatureRange(new Tag('liga'), 2, 4)]);
        self::assertSame([4, 5, 6], $this->ids((new SubstitutionEngine())->apply($this->fixture(), $selection, $this->sequence([4, 5, 4, 5]))));
    }

    public function testSharedLookupUnionsFeatureRangesAndRunsOnce(): void
    {
        $table = new GsubTable(
            new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0, 1])))]),
            new FeatureList([new FeatureRecord(new Tag('aaaa'), new Feature([0])), new FeatureRecord(new Tag('bbbb'), new Feature([0]))]),
            new LookupList([new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1), new GlyphId(2)]), 1)])]),
            4
        );
        $selection = new FeatureSelection(new Tag('latn'), ranges: [new FeatureRange(new Tag('aaaa'), 0, 2), new FeatureRange(new Tag('bbbb'), 1, 3)]);
        self::assertSame([2, 2, 2, 1], $this->ids((new SubstitutionEngine())->apply($table, $selection, $this->sequence([1, 1, 1, 1]))));
    }

    public function testRequiredLanguageFeatureCannotBeDisabledByOptionalRanges(): void
    {
        $selection = new FeatureSelection(new Tag('latn'), new Tag('TRK '), ranges: [new FeatureRange(new Tag('locl'), 0, 2, false)]);
        self::assertSame([14, 3], $this->ids((new SubstitutionEngine())->apply($this->fixture(), $selection, $this->sequence([2, 3]))));
    }

    public function testRangeInspectionConsumesTheExecutionBudget(): void
    {
        $selection = new FeatureSelection(new Tag('latn'), ranges: array_fill(0, 50, new FeatureRange(new Tag('liga'), 0, 2)));
        $this->expectException(MalformedFontException::class);
        (new SubstitutionEngine(new ReadLimits(maxSubstitutionOperations: 20)))->apply($this->fixture(), $selection, $this->sequence([4, 5]));
    }

    /** @return iterable<array{int, int}> */
    public static function invalidRanges(): iterable
    {
        yield [-1, 1];
        yield [1, 1];
        yield [2, 1];
    }

    #[DataProvider('invalidRanges')]
    public function testInvalidRanges(int $start, int $end): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FeatureRange(new Tag('liga'), $start, $end);
    }
}
