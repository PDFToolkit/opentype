<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\CoverageFormat2;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\RangeRecord;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Substitution\AlternateSet;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\Ligature;
use PDFToolkit\OpenType\Substitution\LigatureSet;
use PDFToolkit\OpenType\Substitution\Sequence;
use PDFToolkit\OpenType\Substitution\SingleSubstFormat2;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LayoutModelTest extends TestCase
{
    public function testCoverageIndicesAreExactForBothFormats(): void
    {
        $coverage1 = new CoverageFormat1([new GlyphId(2), new GlyphId(4), new GlyphId(5), new GlyphId(9)]);
        $coverage2 = new CoverageFormat2([
            new RangeRecord(new GlyphId(2), new GlyphId(2), 0),
            new RangeRecord(new GlyphId(4), new GlyphId(5), 1),
            new RangeRecord(new GlyphId(9), new GlyphId(9), 3),
        ]);
        foreach ([$coverage1, $coverage2] as $coverage) {
            self::assertSame(4, $coverage->count());
            foreach ([0 => null, 2 => 0, 3 => null, 4 => 1, 5 => 2, 8 => null, 9 => 3, 10 => null] as $glyph => $index) {
                self::assertSame($index, $coverage->indexOf(new GlyphId($glyph)));
            }
            self::assertEquals($coverage1->glyphs(), $coverage->glyphs());
        }
    }

    public function testGlyphClustersDefaultToInputPositionsAndCannotBeMutated(): void
    {
        $sequence = new GlyphSequence([new GlyphId(5), new GlyphId(7)]);
        self::assertSame([0, 1], $sequence->clusters);
        $this->expectException(\Error::class);
        $sequence->glyphs[] = new GlyphId(9);
    }

    public function testDuplicateFeatureTagsArePermittedForDifferentLanguageSystems(): void
    {
        $list = new FeatureList([
            new FeatureRecord(new Tag('liga'), new Feature([0])),
            new FeatureRecord(new Tag('liga'), new Feature([1])),
        ]);
        self::assertCount(2, $list->featureRecords);
    }

    #[DataProvider('invalidModels')]
    public function testInvalidModelsAreRejected(\Closure $build, string $exception): void
    {
        $this->expectException($exception);
        $build();
    }

    public static function invalidModels(): iterable
    {
        yield 'coverage ordering' => [fn () => new CoverageFormat1([new GlyphId(2), new GlyphId(1)]), MalformedFontException::class];
        yield 'coverage duplicate' => [fn () => new CoverageFormat1([new GlyphId(2), new GlyphId(2)]), MalformedFontException::class];
        yield 'range reversed' => [fn () => new RangeRecord(new GlyphId(3), new GlyphId(2), 0), MalformedFontException::class];
        yield 'range coverage gap' => [fn () => new CoverageFormat2([new RangeRecord(new GlyphId(1), new GlyphId(3), 1)]), MalformedFontException::class];
        yield 'range overlap' => [fn () => new CoverageFormat2([new RangeRecord(new GlyphId(1), new GlyphId(3), 0), new RangeRecord(new GlyphId(3), new GlyphId(4), 3)]), MalformedFontException::class];
        yield 'script ordering' => [fn () => new ScriptList([new ScriptRecord(new Tag('latn'), new Script(null)), new ScriptRecord(new Tag('grek'), new Script(null))]), MalformedFontException::class];
        yield 'DFLT requires language' => [fn () => new ScriptRecord(new Tag('DFLT'), new Script(null)), MalformedFontException::class];
        yield 'reserved flags' => [fn () => new LookupFlags(0x20), MalformedFontException::class];
        yield 'filter set missing' => [fn () => new LookupFlags(0x10), MalformedFontException::class];
        yield 'unexpected filter set' => [fn () => new LookupFlags(0, 1), MalformedFontException::class];
        yield 'single count mismatch' => [fn () => new SingleSubstFormat2(new CoverageFormat1([new GlyphId(1)]), []), MalformedFontException::class];
        yield 'empty multiple' => [fn () => new Sequence([]), MalformedFontException::class];
        yield 'glyph type' => [fn () => new GlyphSequence([1]), \InvalidArgumentException::class];
        yield 'cluster type' => [fn () => new GlyphSequence([new GlyphId(1)], ['0']), \InvalidArgumentException::class];
        yield 'cluster length' => [fn () => new GlyphSequence([new GlyphId(1)], []), \InvalidArgumentException::class];
        yield 'negative cluster' => [fn () => new GlyphSequence([new GlyphId(1)], [-1]), \InvalidArgumentException::class];
        yield 'feature type' => [fn () => new FeatureSelection(new Tag('latn'), features: ['liga']), \InvalidArgumentException::class];
        yield 'lookup index type' => [fn () => new Feature(['0']), MalformedFontException::class];
        yield 'language index type' => [fn () => new LangSys(null, [false]), MalformedFontException::class];
        yield 'ligature component type' => [fn () => new Ligature(new GlyphId(1), [false]), \InvalidArgumentException::class];
        yield 'ligature set type' => [fn () => new LigatureSet([null]), \InvalidArgumentException::class];
    }
}
