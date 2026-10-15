<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
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
use PDFToolkit\OpenType\Substitution\AlternateIndex;
use PDFToolkit\OpenType\Substitution\AlternateSelector;
use PDFToolkit\OpenType\Substitution\AlternateSet;
use PDFToolkit\OpenType\Substitution\AlternateSubstFormat1;
use PDFToolkit\OpenType\Substitution\ExtensionSubstFormat1;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\GsubTable;
use PDFToolkit\OpenType\Substitution\Ligature;
use PDFToolkit\OpenType\Substitution\LigatureSet;
use PDFToolkit\OpenType\Substitution\LigatureSubstFormat1;
use PDFToolkit\OpenType\Substitution\MultipleSubstFormat1;
use PDFToolkit\OpenType\Substitution\Sequence;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat1;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat2;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Substitution\UnsupportedSubstitution;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SubstitutionEngineTest extends TestCase
{
    #[DataProvider('fixtureCases')]
    public function testRealFontRules(array $input, array $features, array $expected, array $clusters, ?int $alternate = null, ?string $language = null): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitLayout.ttf'));
        $table = (new GsubReader())->read($font->table(new Tag('GSUB')), $font->glyphCount());
        $selection = new FeatureSelection(new Tag('latn'), $language === null ? null : new Tag($language), array_map(fn (string $tag): Tag => new Tag($tag), $features), $alternate === null ? null : new AlternateIndex($alternate));
        $output = (new SubstitutionEngine())->apply($table, $selection, $this->sequence($input));
        self::assertSame($expected, $this->ids($output));
        self::assertSame($clusters, $output->clusters);
    }

    public static function fixtureCases(): iterable
    {
        yield 'ffi' => [[4, 4, 5], ['liga'], [7], [0]];
        yield 'fi' => [[4, 5], ['liga'], [6], [0]];
        yield 'two ligatures' => [[4, 4, 5, 4, 5], ['liga'], [7, 6], [0, 3]];
        yield 'small caps' => [[2, 3], ['smcp'], [9, 10], [0, 1]];
        yield 'alternate one' => [[2, 3], ['salt'], [11, 3], [0, 1], 1];
        yield 'alternate two' => [[2, 2], ['salt'], [12, 12], [0, 1], 2];
        yield 'decomposition' => [[6], ['ccmp'], [4, 5], [0, 0]];
        yield 'extension' => [[2, 3], ['vrt2'], [13, 3], [0, 1]];
        yield 'no default features' => [[2, 4, 5], [], [2, 4, 5], [0, 1, 2]];
        yield 'missing feature' => [[2, 3], ['zzzz'], [2, 3], [0, 1]];
        yield 'required Turkish feature' => [[2, 3], [], [14, 3], [0, 1], null, 'TRK '];
        yield 'French default fallback' => [[2, 3], ['smcp'], [9, 10], [0, 1], null, 'FRA '];
    }

    public function testLookupUnionUsesLookupListOrderOnlyOnce(): void
    {
        $coverage = new CoverageFormat1([new GlyphId(1)]);
        $later = new CoverageFormat1([new GlyphId(2)]);
        $table = $this->table([
            new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1($coverage, 1)]),
            new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1($later, 1)]),
        ], [1, 0, 0, 1]);
        self::assertSame([3], $this->ids((new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([1]))));
    }

    public function testFirstSuccessfulSubtableWinsAndItsOutputIsNotReprocessed(): void
    {
        $coverage = new CoverageFormat1([new GlyphId(1), new GlyphId(2)]);
        $table = $this->table([new Lookup(1, new LookupFlags(0), [
            new SingleSubstFormat2($coverage, [new GlyphId(2), new GlyphId(3)]),
            new SingleSubstFormat2($coverage, [new GlyphId(4), new GlyphId(5)]),
        ])]);
        self::assertSame([2, 3], $this->ids((new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([1, 2]))));
    }

    public function testLigaturePreferenceUsesFontOrderAndEarliestInputCluster(): void
    {
        $table = $this->table([new Lookup(4, new LookupFlags(0), [
            new LigatureSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [
                new LigatureSet([
                    new Ligature(new GlyphId(8), [new GlyphId(2)]),
                    new Ligature(new GlyphId(9), [new GlyphId(2), new GlyphId(3)]),
                ]),
            ]),
        ])]);
        $output = (new SubstitutionEngine())->apply($table, $this->selection(), new GlyphSequence([new GlyphId(1), new GlyphId(2), new GlyphId(3)], [5, 2, 7]));
        self::assertSame([8, 3], $this->ids($output));
        self::assertSame([2, 7], $output->clusters);
    }

    public function testEmptySequenceRemainsEmpty(): void
    {
        self::assertSame([], (new SubstitutionEngine())->apply($this->singleTable(), $this->selection(), new GlyphSequence([]))->glyphs);
    }

    public function testScriptAndLanguageFallbackCanBeDisabled(): void
    {
        $script = new Script(new LangSys(null, [0]));
        $table = new GsubTable(
            new ScriptList([new ScriptRecord(new Tag('DFLT'), $script), new ScriptRecord(new Tag('latn'), $script)]),
            new FeatureList([new FeatureRecord(new Tag('test'), new Feature([0]))]),
            new LookupList([new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), 1)])]),
            16,
        );
        $engine = new SubstitutionEngine();
        self::assertSame([2], $this->ids($engine->apply($table, new FeatureSelection(new Tag('grek'), features: [new Tag('test')]), $this->sequence([1]))));
        self::assertSame([1], $this->ids($engine->apply($table, new FeatureSelection(new Tag('grek'), features: [new Tag('test')], fallbackToDefaultScript: false), $this->sequence([1]))));
        self::assertSame([1], $this->ids($engine->apply($table, new FeatureSelection(new Tag('latn'), new Tag('FRA '), [new Tag('test')], fallbackToDefaultLanguage: false), $this->sequence([1]))));
    }

    public function testUnsupportedLookupFailsBeforeAnySelectedAlternatePolicyRuns(): void
    {
        $selector = new class () implements AlternateSelector {
            public int $calls = 0;
            public function select(GlyphId $input, AlternateSet $alternates): GlyphId
            {
                ++$this->calls;
                return $alternates->alternateGlyphIds[0];
            }
        };
        $table = $this->table([
            new Lookup(3, new LookupFlags(0), [new AlternateSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [new AlternateSet([new GlyphId(2)])])]),
            new Lookup(5, new LookupFlags(0), [new UnsupportedSubstitution(5, 1, new BinaryView(''))]),
        ], [0, 1]);
        try {
            (new SubstitutionEngine())->apply($table, new FeatureSelection(new Tag('latn'), features: [new Tag('test')], alternateSelector: $selector), $this->sequence([1]));
            self::fail('An unsupported selected lookup was silently ignored.');
        } catch (UnsupportedFontException) {
            self::assertSame(0, $selector->calls);
        }
    }

    public function testSelectedEmptyBaseFeatureStillRequiresVariationEvaluation(): void
    {
        $table = new GsubTable(
            new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0])))]),
            new FeatureList([new FeatureRecord(new Tag('test'), new Feature([]))]),
            new LookupList([]),
            16,
            1,
            new BinaryView(''),
        );
        $this->expectException(UnsupportedFontException::class);
        (new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([1]));
    }

    public function testUnknownSubtableImplementationsFailBeforeAlternatePolicyCalls(): void
    {
        $subtable = new class () implements \PDFToolkit\OpenType\Substitution\SubstitutionSubtable {
            public function lookupType(): int
            {
                return 1;
            }
            public function isSupported(): bool
            {
                return true;
            }
        };
        $this->expectException(UnsupportedFontException::class);
        (new SubstitutionEngine())->apply($this->table([new Lookup(1, new LookupFlags(0), [$subtable])]), $this->selection(), $this->sequence([]));
    }

    public function testUnsupportedUnselectedLookupIsPreserved(): void
    {
        $table = $this->table([new Lookup(6, new LookupFlags(0), [new UnsupportedSubstitution(6, 3, new BinaryView(''))])]);
        self::assertSame([1], $this->ids((new SubstitutionEngine())->apply($table, new FeatureSelection(new Tag('latn')), $this->sequence([1]))));
    }

    public function testAlternateChoiceCannotEscapeTheDefinedSet(): void
    {
        $selector = new class () implements AlternateSelector {
            public function select(GlyphId $input, AlternateSet $alternates): GlyphId
            {
                return new GlyphId(15);
            }
        };
        $table = $this->table([new Lookup(3, new LookupFlags(0), [new AlternateSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [new AlternateSet([new GlyphId(2)])])])]);
        $this->expectException(\InvalidArgumentException::class);
        (new SubstitutionEngine())->apply($table, new FeatureSelection(new Tag('latn'), features: [new Tag('test')], alternateSelector: $selector), $this->sequence([1]));
    }

    public function testMissingAlternatePolicyFailsEvenBeforeAnEmptyInputRun(): void
    {
        $table = $this->table([new Lookup(3, new LookupFlags(0), [new AlternateSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [new AlternateSet([new GlyphId(2)])])])]);
        $this->expectException(UnsupportedFontException::class);
        (new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([]));
    }

    public function testNonzeroLookupFlagsWithoutGlyphClassesDoNotInventClasses(): void
    {
        $table = $this->table([new Lookup(1, new LookupFlags(8), [new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), 1)])]);
        self::assertSame([2], $this->ids((new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([1]))));
    }

    public function testOutputExpansionLimitDoesNotMutateTheInput(): void
    {
        $table = $this->table([new Lookup(2, new LookupFlags(0), [
            new MultipleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [new Sequence([new GlyphId(1), new GlyphId(2)])]),
        ])]);
        $input = $this->sequence([1, 1]);
        try {
            (new SubstitutionEngine(new ReadLimits(maxOutputGlyphs: 3)))->apply($table, $this->selection(), $input);
            self::fail('Expansion limit was not enforced.');
        } catch (MalformedFontException) {
            self::assertSame([1, 1], $this->ids($input));
        }
    }

    public function testOperationBudgetAppliesDuringSelectionAndMatching(): void
    {
        $this->expectException(MalformedFontException::class);
        (new SubstitutionEngine(new ReadLimits(maxSubstitutionOperations: 2)))->apply($this->singleTable(), $this->selection(), $this->sequence([1]));
    }

    public function testRepeatedFeatureReferencesAreDeduplicatedBeforeLookupExpansion(): void
    {
        $lookup = new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), 1)]);
        $table = new GsubTable(
            new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, array_fill(0, 100, 0))))]),
            new FeatureList([new FeatureRecord(new Tag('test'), new Feature(array_fill(0, 100, 0)))]),
            new LookupList([$lookup]),
            16,
        );
        // 1 requested tag + 100 language indices + 100 lookup indices + execution entry + match.
        self::assertSame([2], $this->ids((new SubstitutionEngine(new ReadLimits(maxSubstitutionOperations: 203)))->apply($table, $this->selection(), $this->sequence([1]))));
    }

    public function testAlternateMembershipSearchConsumesTheOperationBudget(): void
    {
        $table = $this->table([new Lookup(3, new LookupFlags(0), [
            new AlternateSubstFormat1(new CoverageFormat1([new GlyphId(1)]), [
                new AlternateSet([new GlyphId(2), new GlyphId(3), new GlyphId(4), new GlyphId(5)]),
            ]),
        ])]);
        $this->expectException(MalformedFontException::class);
        (new SubstitutionEngine(new ReadLimits(maxSubstitutionOperations: 6)))->apply(
            $table,
            new FeatureSelection(new Tag('latn'), features: [new Tag('test')], alternateSelector: new AlternateIndex(4)),
            $this->sequence([1]),
        );
    }

    public function testConstructedSubstitutionsCannotReferBeyondTheFont(): void
    {
        $this->expectException(MalformedFontException::class);
        $this->table([new Lookup(1, new LookupFlags(0), [new SingleSubstFormat2(new CoverageFormat1([new GlyphId(1)]), [new GlyphId(16)])])]);
    }

    public function testExtensionsRetainTheirOwnObjects(): void
    {
        $extension = new ExtensionSubstFormat1(1, new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), 1));
        $table = $this->table([new Lookup(7, new LookupFlags(0), [$extension])]);
        self::assertSame(7, $extension->lookupType());
        self::assertSame(1, $extension->extensionLookupType);
        self::assertSame([2], $this->ids((new SubstitutionEngine())->apply($table, $this->selection(), $this->sequence([1]))));
    }

    private function singleTable(): GsubTable
    {
        return $this->table([new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1(new CoverageFormat1([new GlyphId(1)]), 1)])]);
    }

    /** @param list<Lookup> $lookups @param list<int> $indices */
    private function table(array $lookups, array $indices = [0]): GsubTable
    {
        return new GsubTable(new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0])))]), new FeatureList([new FeatureRecord(new Tag('test'), new Feature($indices))]), new LookupList($lookups), 16);
    }

    private function selection(): FeatureSelection
    {
        return new FeatureSelection(new Tag('latn'), features: [new Tag('test')]);
    }

    private function sequence(array $glyphs): GlyphSequence
    {
        return new GlyphSequence(array_map(fn (int $id): GlyphId => new GlyphId($id), $glyphs));
    }

    private function ids(GlyphSequence $sequence): array
    {
        return array_map(fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs);
    }
}
