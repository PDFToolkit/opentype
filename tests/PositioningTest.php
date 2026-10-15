<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\ContextRule;
use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\CoveredGlyph;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Layout\SequenceLookupRecord;
use PDFToolkit\OpenType\Positioning\Anchor;
use PDFToolkit\OpenType\Positioning\AnchorPoint;
use PDFToolkit\OpenType\Positioning\AnchorPointResolver;
use PDFToolkit\OpenType\Positioning\AttachmentAnchors;
use PDFToolkit\OpenType\Positioning\ContextPositioning;
use PDFToolkit\OpenType\Positioning\DeviceAdjustment;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\GposTable;
use PDFToolkit\OpenType\Positioning\MarkAttachment;
use PDFToolkit\OpenType\Positioning\MarkRecord;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Positioning\PositioningLookup;
use PDFToolkit\OpenType\Positioning\SingleAdjustment;
use PDFToolkit\OpenType\Positioning\ValueRecord;
use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositioningTest extends TestCase
{
    public function testEveryTruncationOfTheAdvancedGposTableIsRejected(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAdvanced.ttf'));
        $view = $font->table(new Tag('GPOS'));
        $bytes = $view->bytes(0, $view->size());
        for ($i = 0; $i < strlen($bytes); ++$i) {
            try {
                (new GposReader())->read(new BinaryView(substr($bytes, 0, $i)), $font->glyphCount());
                self::fail('A truncated GPOS was accepted at byte '.$i);
            } catch (MalformedFontException|UnsupportedFontException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAggregateRecordAndByteBudgetsAreEnforced(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAdvanced.ttf'));
        foreach ([new ReadLimits(maxLayoutRecords: 1), new ReadLimits(maxBytes: 10)] as $limit) {
            try {
                (new GposReader($limit))->read($font->table(new Tag('GPOS')), $font->glyphCount());
                self::fail('GPOS ignored its explicit read budget.');
            } catch (MalformedFontException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testGdefClassificationAndMarkSetsAreReadFromTheRealFont(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAdvanced.ttf'));
        $definition = (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount());
        self::assertSame(1, $definition->glyphClassDef->classOf(new GlyphId(2)));
        self::assertSame(2, $definition->glyphClassDef->classOf(new GlyphId(8)));
        self::assertSame(3, $definition->glyphClassDef->classOf(new GlyphId(9)));
        self::assertSame([9, 11], array_map(fn (GlyphId $glyph): int => $glyph->value, $definition->markGlyphSets[0]->glyphs()));
        self::assertTrue($definition->ignores(new GlyphId(10), new LookupFlags(16, 0)));
        self::assertFalse($definition->ignores(new GlyphId(9), new LookupFlags(16, 0)));
    }

    public function testWritingDirectionSelectsOnlyTheCorrespondingAdvance(): void
    {
        $value = new ValueRecord(10, 20, 30, 40);
        $initial = new GlyphPosition(600, -1000);
        self::assertEquals(new GlyphPosition(630, -1000, 10, 20), $initial->adjusted($value, new PositioningContext()));
        self::assertEquals(new GlyphPosition(600, -960, 10, 20), $initial->adjusted($value, new PositioningContext(vertical: true)));
    }

    public function testDeviceAdjustmentsRequireAnExplicitPixelSize(): void
    {
        $device = new DeviceAdjustment(10, [-2, -1, 0, 1]);
        $anchor = new Anchor(100, 200, xAdjustment: $device);
        self::assertEquals(new AnchorPoint(100, 200), $anchor->resolve(new GlyphId(1), new PositioningContext()));
        self::assertEquals(new AnchorPoint(-100, 200), $anchor->resolve(new GlyphId(1), new PositioningContext(pixelsPerEm: 10)));
        self::assertSame(0, $device->delta(9));
        self::assertSame(1, $device->delta(13));
    }

    public function testAnchorPointResolutionIsAnExplicitCapability(): void
    {
        $point = new Anchor(100, 200, 7);
        $resolver = new class () implements AnchorPointResolver {
            public function point(GlyphId $glyph, int $pointIndex): AnchorPoint
            {
                return new AnchorPoint($glyph->value * 10, $pointIndex * 20);
            }
        };
        self::assertEquals(new AnchorPoint(100, 200), $point->resolve(new GlyphId(3), new PositioningContext()));
        self::assertEquals(new AnchorPoint(30, 140), $point->resolve(new GlyphId(3), new PositioningContext(outlinePoints: $resolver)));
    }

    public function testVariationIndicesCannotSilentlyBecomeZero(): void
    {
        $this->expectException(UnsupportedFontException::class);
        (new PositioningContext())->delta(new VariationIndex(0, 0));
    }

    public function testMarkAttachmentsFollowLaterBaseAdjustmentsAndPreserveInput(): void
    {
        $mark = new MarkAttachment(4, $this->coverage(2), $this->coverage(1), 1, [new MarkRecord(0, new Anchor(0, 100))], [new AttachmentAnchors([[new Anchor(250, 700)]])]);
        $baseMove = new SingleAdjustment($this->coverage(1), [new ValueRecord(10, 20, -50)], 1);
        $table = $this->table([new PositioningLookup(4, new LookupFlags(0), [$mark]), new PositioningLookup(1, new LookupFlags(0), [$baseMove])]);
        $input = new PositionedGlyphSequence(new GlyphSequence([new GlyphId(1), new GlyphId(2)]), [new GlyphPosition(600), new GlyphPosition()]);
        $result = (new PositioningEngine())->apply($table, $this->selection(), $input);
        self::assertEquals(new GlyphPosition(550, 0, 10, 20), $result->positions[0]);
        self::assertEquals(new GlyphPosition(0, 0, -290, 620), $result->positions[1]);
        self::assertEquals(new GlyphPosition(600), $input->positions[0]);
    }

    public function testNestedPositioningCyclesAreBounded(): void
    {
        $rule = new ContextRule([new CoveredGlyph($this->coverage(1))], [new SequenceLookupRecord(0, 0)]);
        $table = $this->table([new PositioningLookup(7, new LookupFlags(0), [new ContextPositioning(7, 3, $this->coverage(1), [$rule])])]);
        $this->expectException(MalformedFontException::class);
        (new PositioningEngine(new ReadLimits(maxLookupNesting: 3)))->apply($table, $this->selection(), new PositionedGlyphSequence(new GlyphSequence([new GlyphId(1)]), [new GlyphPosition(600)]));
    }

    public function testPositioningOperationsAreBoundedEvenWhenNoRuleMatches(): void
    {
        $table = $this->table([new PositioningLookup(1, new LookupFlags(0), [new SingleAdjustment($this->coverage(1), [new ValueRecord(xAdvance: -20)], 1)])]);
        $input = new PositionedGlyphSequence(new GlyphSequence(array_fill(0, 20, new GlyphId(2))), array_fill(0, 20, new GlyphPosition(600)));
        $this->expectException(MalformedFontException::class);
        (new PositioningEngine(new ReadLimits(maxSubstitutionOperations: 12)))->apply($table, $this->selection(), $input);
    }

    #[DataProvider('invalidTables')]
    public function testMalformedGposStructuresAreRejected(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new GposReader())->read(new BinaryView($bytes), 16);
    }

    public static function invalidTables(): iterable
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitAdvanced.ttf'));
        $table = $font->table(new Tag('GPOS'));
        $bytes = $table->bytes(0, $table->size());
        $lookupAt = $table->uint16(8);
        $firstLookup = $lookupAt + $table->uint16($lookupAt + 2);
        yield 'unknown lookup' => [substr_replace($bytes, pack('n', 10), $firstLookup, 2)];
        yield 'reserved flags' => [substr_replace($bytes, pack('n', 0x20), $firstLookup + 2, 2)];
        yield 'null lookup list' => [substr_replace($bytes, pack('n', 0), 8, 2)];
        $subtable = $firstLookup + $table->uint16($firstLookup + 6);
        yield 'unknown single format' => [substr_replace($bytes, pack('n', 3), $subtable, 2)];
        yield 'reserved value format' => [substr_replace($bytes, pack('n', 0x100), $subtable + 4, 2)];
        yield 'coverage inside header' => [substr_replace($bytes, pack('n', 2), $subtable + 2, 2)];
    }

    private function coverage(int ...$ids): CoverageFormat1
    {
        return new CoverageFormat1(array_map(fn (int $id): GlyphId => new GlyphId($id), $ids));
    }

    private function table(array $lookups): GposTable
    {
        return new GposTable(new ScriptList([new ScriptRecord(new Tag('latn'), new Script(new LangSys(null, [0])))]), new FeatureList([new FeatureRecord(new Tag('test'), new Feature(array_keys($lookups)))]), $lookups, 16);
    }

    private function selection(): FeatureSelection
    {
        return new FeatureSelection(new Tag('latn'), features: [new Tag('test')]);
    }
}
