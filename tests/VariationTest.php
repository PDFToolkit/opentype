<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FeatureVariationRecord;
use PDFToolkit\OpenType\Variation\FeatureVariations;
use PDFToolkit\OpenType\Variation\ItemVariationStoreReader;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\UnknownFeatureCondition;
use PDFToolkit\OpenType\Variation\VariationCondition;
use PDFToolkit\OpenType\Variation\VariationInstance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VariationTest extends TestCase
{
    public function testGdefAttachmentPointsAndCaretsAreTypedAndResolvable(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitVariable.ttf'));
        $definition = (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount());
        self::assertSame([0, 1], $definition->attachPoints[2]);
        self::assertSame(200.0, $definition->ligatureCarets[8][0]->resolve(new GlyphId(8), new PositioningContext()));
        self::assertNotNull($definition->itemVariationStore);
        $instance = new VariationInstance($definition->itemVariationStore, new NormalizedCoordinates([0.5]));
        self::assertSame(50.0, $instance->delta(new VariationIndex(0, 0)));
        self::assertSame(-40.0, $instance->delta(new VariationIndex(0, 1)));
    }

    public function testSignedShortAndByteDeltaRowsAreInterpolated(): void
    {
        $store = (new ItemVariationStoreReader())->read(new BinaryView(self::store()), new LayoutReadBudget(100));
        foreach ([[-1.0, -40.0], [-0.5, -20.0], [0.0, 0.0], [0.5, 50.0], [1.0, 100.0]] as [$coordinate, $expected]) {
            self::assertSame($expected, $store->delta(new VariationIndex(0, 0), new NormalizedCoordinates([$coordinate])));
            self::assertSame($expected, (new VariationInstance($store, new NormalizedCoordinates([$coordinate])))->delta(new VariationIndex(0, 0)));
        }
        self::assertSame(0.0, $store->delta(new VariationIndex(65535, 65535), new NormalizedCoordinates([])));
    }

    public function testLongWordDeltasRemainSigned32BitAndSigned16Bit(): void
    {
        $base = substr(self::store(), 0, 28);
        $bytes = $base.self::words(1, 0x8001, 2, 0, 1).pack('N', (-100000) & 0xFFFFFFFF).self::words(-300);
        $store = (new ItemVariationStoreReader())->read(new BinaryView($bytes), new LayoutReadBudget(100));
        self::assertSame(-100000.0, $store->delta(new VariationIndex(0, 0), new NormalizedCoordinates([1.0])));
        self::assertSame(-300.0, $store->delta(new VariationIndex(0, 0), new NormalizedCoordinates([-1.0])));
    }

    public function testNullItemVariationDataOffsetsRemainAbsent(): void
    {
        $bytes = substr_replace(substr(self::store(), 0, 28), pack('N', 0), 8, 4);
        $store = (new ItemVariationStoreReader())->read(new BinaryView($bytes), new LayoutReadBudget(100));
        self::assertSame([null], $store->data);
        self::assertSame(0.0, $store->delta(new VariationIndex(0, 42), new NormalizedCoordinates([1.0])));
    }

    public function testFeatureConditionsUseFirstMatchIncludingUniversalNoChange(): void
    {
        $base = new FeatureList([new FeatureRecord(new Tag('test'), new Feature([0]))]);
        $variations = new FeatureVariations([
            new FeatureVariationRecord([new UnknownFeatureCondition(999)], [0 => new Feature([1])]),
            new FeatureVariationRecord([new VariationCondition(3, 0, 1)], [0 => new Feature([1])]),
            new FeatureVariationRecord([], [], false),
            new FeatureVariationRecord([], []),
            new FeatureVariationRecord([], [0 => new Feature([2])]),
        ]);
        self::assertSame([0], $variations->evaluate($base, new NormalizedCoordinates([0.75]), new LayoutReadBudget(100))->featureRecords[0]->feature->lookupListIndices);
        $variations = new FeatureVariations([new FeatureVariationRecord([new VariationCondition(0, 0.5, 1)], [0 => new Feature([1])])]);
        self::assertSame([1], $variations->evaluate($base, new NormalizedCoordinates([0.5]), new LayoutReadBudget(100))->featureRecords[0]->feature->lookupListIndices);
        self::assertSame([0], $variations->evaluate($base, new NormalizedCoordinates([0.499]), new LayoutReadBudget(100))->featureRecords[0]->feature->lookupListIndices);
    }

    public function testEveryTruncatedVariableLayoutTableIsRejected(): void
    {
        $font = (new SfntReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitVariable.ttf'));
        foreach (['GSUB' => new GsubReader(), 'GPOS' => new GposReader(), 'GDEF' => new GdefReader()] as $tag => $reader) {
            $view = $font->table(new Tag($tag));
            $bytes = $view->bytes(0, $view->size());
            for ($i = 0; $i < strlen($bytes); ++$i) {
                try {
                    $reader->read(new BinaryView(substr($bytes, 0, $i)), $font->glyphCount());
                    self::fail('Truncated '.$tag.' was accepted at byte '.$i);
                } catch (MalformedFontException|UnsupportedFontException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }

    #[DataProvider('malformedStores')]
    public function testMalformedVariationDataIsRejectedBeforeAllocation(string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new ItemVariationStoreReader())->read(new BinaryView($bytes), new LayoutReadBudget(100));
    }

    public static function malformedStores(): iterable
    {
        yield 'region inside header' => [substr_replace(self::store(), pack('N', 2), 2, 4)];
        yield 'unknown region' => [substr_replace(self::store(), self::words(2), 34, 2)];
        yield 'word count exceeds region count' => [substr_replace(self::store(), self::words(3), 30, 2)];
        yield 'reserved region count bit' => [substr_replace(self::store(), self::words(0x8002), 14, 2)];
        yield 'out of range normalized coordinate' => [substr_replace(self::store(), self::words(20000), 18, 2)];
        yield 'truncated row' => [substr(self::store(), 0, -1)];
    }

    public function testVariationRecordBudgetIsCumulative(): void
    {
        $this->expectException(MalformedFontException::class);
        (new ItemVariationStoreReader())->read(new BinaryView(self::store()), new LayoutReadBudget(3));
    }

    private static function store(): string
    {
        return self::words(1).pack('N', 12).self::words(1).pack('N', 28)
            .self::words(1, 2, 0, 16384, 16384, -16384, -16384, 0)
            .self::words(2, 1, 2, 0, 1).self::words(100).pack('C', 216).self::words(-80).pack('C', 60);
    }

    private static function words(int ...$words): string
    {
        return pack('n*', ...array_map(fn (int $word): int => $word & 65535, $words));
    }
}
