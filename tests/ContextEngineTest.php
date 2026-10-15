<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\ClassDefinition;
use PDFToolkit\OpenType\Layout\ClassRange;
use PDFToolkit\OpenType\Layout\ContextRule;
use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\CoveredGlyph;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\GdefTable;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\LookupList;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Layout\SequenceLookupRecord;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\ContextSubstitution;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubTable;
use PDFToolkit\OpenType\Substitution\Ligature;
use PDFToolkit\OpenType\Substitution\LigatureSet;
use PDFToolkit\OpenType\Substitution\LigatureSubstFormat1;
use PDFToolkit\OpenType\Substitution\MultipleSubstFormat1;
use PDFToolkit\OpenType\Substitution\ReverseChainSingleSubstitution;
use PDFToolkit\OpenType\Substitution\Sequence;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat1;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\TestCase;

final class ContextEngineTest extends TestCase
{
    public function testNestedActionsApplyOnlyAtTheMatchedPosition(): void
    {
        $context = new ContextSubstitution(5, 3, $this->coverage(1), [new ContextRule([$this->predicate(1), $this->predicate(2)], [new SequenceLookupRecord(0, 1)])]);
        $table = $this->table([new Lookup(5, new LookupFlags(0), [$context]), new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1($this->coverage(1), 3)])]);
        self::assertSame([1, 4, 2, 1], $this->ids($this->apply($table, [1, 1, 2, 1])));
    }

    public function testBacktrackIsNearestFirstAndLookaheadIsNotConsumed(): void
    {
        $rule = new ContextRule([$this->predicate(1)], [new SequenceLookupRecord(0, 1)], [$this->predicate(2), $this->predicate(3)], [$this->predicate(1)]);
        $table = $this->table([new Lookup(6, new LookupFlags(0), [new ContextSubstitution(6, 3, $this->coverage(1), [$rule])]), new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1($this->coverage(1), 3)])]);
        self::assertSame([3, 2, 4, 1], $this->ids($this->apply($table, [3, 2, 1, 1])));
        self::assertSame([2, 3, 1, 1], $this->ids($this->apply($table, [2, 3, 1, 1])));
    }

    public function testReverseLookupSeesReplacementsOnItsRight(): void
    {
        $coverage = $this->coverage(1);
        $reverse = new ReverseChainSingleSubstitution($coverage, new ContextRule([$this->predicate(1)], [], [], [$this->predicate(2)]), [new GlyphId(2)]);
        self::assertSame([2, 2, 2], $this->ids($this->apply($this->table([new Lookup(8, new LookupFlags(0), [$reverse])]), [1, 1, 2])));
    }

    public function testNestedSequenceIndicesAddressTheExpandedInput(): void
    {
        $rule = new ContextRule([$this->predicate(1)], [new SequenceLookupRecord(0, 1), new SequenceLookupRecord(1, 2)]);
        $table = $this->table([
            new Lookup(5, new LookupFlags(0), [new ContextSubstitution(5, 3, $this->coverage(1), [$rule])]),
            new Lookup(2, new LookupFlags(0), [new MultipleSubstFormat1($this->coverage(1), [new Sequence([new GlyphId(1), new GlyphId(2)])])]),
            new Lookup(1, new LookupFlags(0), [new SingleSubstFormat1($this->coverage(2), 1)]),
        ]);
        self::assertSame([1, 3], $this->ids($this->apply($table, [1])));
    }

    public function testIgnoredMarksSurviveLigatureAndComponentProvenanceIsRetained(): void
    {
        $table = $this->table([new Lookup(4, new LookupFlags(8), [new LigatureSubstFormat1($this->coverage(1), [new LigatureSet([new Ligature(new GlyphId(5), [new GlyphId(2)])])])])]);
        $definition = new GdefTable(new ClassDefinition([new ClassRange(1, 2, 1), new ClassRange(3, 3, 3), new ClassRange(5, 5, 2)]));
        $result = $this->apply($table, [1, 3, 2], $definition);
        self::assertSame([5, 3], $this->ids($result));
        self::assertSame([[0, 2], [1]], $result->componentClusters);
        self::assertSame([0, 1], $result->clusters);
    }

    public function testMarkFilteringSetTakesPrecedenceOverAttachmentClass(): void
    {
        $definition = new GdefTable(new ClassDefinition([new ClassRange(2, 3, 3)]), new ClassDefinition([new ClassRange(2, 2, 1)]), [$this->coverage(3)]);
        $flags = new LookupFlags(0x0110, 0);
        self::assertTrue($definition->ignores(new GlyphId(2), $flags));
        self::assertFalse($definition->ignores(new GlyphId(3), $flags));
        self::assertTrue($definition->ignores(new GlyphId(3), new LookupFlags(0x0118, 0)));
    }

    public function testLookupCyclesFailWithinTheExplicitDepthBudget(): void
    {
        $rule = new ContextRule([$this->predicate(1)], [new SequenceLookupRecord(0, 0)]);
        $table = $this->table([new Lookup(5, new LookupFlags(0), [new ContextSubstitution(5, 3, $this->coverage(1), [$rule])])]);
        $this->expectException(MalformedFontException::class);
        (new SubstitutionEngine(new ReadLimits(maxLookupNesting: 3)))->apply($table, new FeatureSelection(new Tag('latn'), features: [new Tag('test')]), new GlyphSequence([new GlyphId(1)]));
    }

    private function coverage(int ...$ids): CoverageFormat1
    {
        return new CoverageFormat1(array_map(fn (int $id): GlyphId => new GlyphId($id), $ids));
    }

    private function predicate(int $id): CoveredGlyph
    {
        return new CoveredGlyph($this->coverage($id));
    }

    private function table(array $lookups): GsubTable
    {
        return new GsubTable(new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0])))]), new FeatureList([new FeatureRecord(new Tag('test'), new Feature([0]))]), new LookupList($lookups), 16);
    }

    private function apply(GsubTable $table, array $ids, ?GdefTable $definition = null): GlyphSequence
    {
        return (new SubstitutionEngine())->apply($table, new FeatureSelection(new Tag('latn'), features: [new Tag('test')]), new GlyphSequence(array_map(fn (int $id): GlyphId => new GlyphId($id), $ids)), $definition);
    }

    private function ids(GlyphSequence $sequence): array
    {
        return array_map(fn (GlyphId $glyph): int => $glyph->value, $sequence->glyphs);
    }
}
