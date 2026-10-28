<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\CharacterVariantParameters;
use PDFToolkit\OpenType\Layout\FeatureParametersReader;
use PDFToolkit\OpenType\Layout\LayoutListsReader;
use PDFToolkit\OpenType\Layout\OpticalSizeFamily;
use PDFToolkit\OpenType\Layout\OpticalSizeParameters;
use PDFToolkit\OpenType\Layout\StylisticSetParameters;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureParametersTest extends TestCase
{
    public function testSizeSemanticsAndRawBytesRemainAvailable(): void
    {
        $bytes = pack('nnnnnnn', 4, 0, 180, 3, 257, 139, 240);
        $feature = (new LayoutListsReader())->feature(new BinaryView($bytes), new Tag('size'), new LayoutReadBudget(100));
        self::assertInstanceOf(OpticalSizeParameters::class, $feature->parameters);
        self::assertSame(180, $feature->parameters->designSize);
        self::assertFalse($feature->parameters->contains(13.9));
        self::assertTrue($feature->parameters->contains(24));
        self::assertSame(substr($bytes, 4), $feature->featureParams?->bytes(0, 10));
    }

    public function testNoSubfamilyMeansRemainingFieldsAreIgnored(): void
    {
        $size = new OpticalSizeParameters(60, 0, 1, 240, 139);
        self::assertFalse($size->contains(6));
        self::assertSame(1, $size->subfamilyNameId);
    }

    public function testOpticalSelectionHonorsExclusiveInclusiveBoundsAndHigherTie(): void
    {
        $caption = new OpticalSizeParameters(70, 3, 257, 50, 80);
        $text = new OpticalSizeParameters(120, 3, 257, 100, 140);
        $display = new OpticalSizeParameters(240, 3, 257, 140, 720);
        $family = new OpticalSizeFamily('Explicit family', [$display, $caption, $text]);
        self::assertSame($caption, $family->select(8));
        self::assertSame($text, $family->select(9));
        self::assertSame($text, $family->select(14));
        self::assertSame($display, $family->select(14.01));
        self::assertSame($display, $family->select(100));
    }

    public function testStylisticSetRetainsNonFontSpecificNameIdPermittedBySpecification(): void
    {
        $params = (new FeatureParametersReader())->read(new BinaryView(pack('nn', 0, 5)), new Tag('ss20'), new LayoutReadBudget(100));
        self::assertInstanceOf(StylisticSetParameters::class, $params);
        self::assertSame(5, $params->uiLabelNameId);
    }

    public function testCharacterVariantNamesAndSupplementaryScalar(): void
    {
        $bytes = pack('nnnnnnn', 0, 256, 257, 258, 2, 300, 2)."\0\0A\1\xF6\0";
        $params = (new FeatureParametersReader())->read(new BinaryView($bytes), new Tag('cv99'), new LayoutReadBudget(100));
        self::assertInstanceOf(CharacterVariantParameters::class, $params);
        self::assertSame(301, $params->parameterNameId(2));
        self::assertNull($params->parameterNameId(3));
        self::assertSame(0x1F600, $params->characters[1]->value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function malformed(): iterable
    {
        yield 'size zero design' => ['size', pack('nnnnn', 0, 0, 0, 0, 0)];
        yield 'size invalid name' => ['size', pack('nnnnn', 120, 1, 1, 100, 140)];
        yield 'size inverted range' => ['size', pack('nnnnn', 120, 1, 256, 140, 100)];
        yield 'cv zero count nonzero name' => ['cv01', pack('nnnnnnn', 0, 0, 0, 0, 0, 256, 0)];
        yield 'cv name range overflow' => ['cv01', pack('nnnnnnn', 0, 0, 0, 0, 2, 32767, 0)];
        yield 'cv surrogate' => ['cv01', pack('nnnnnnn', 0, 0, 0, 0, 0, 0, 1)."\0\xD8\0"];
    }

    #[DataProvider('malformed')]
    public function testMalformedParameters(string $tag, string $bytes): void
    {
        $this->expectException(MalformedFontException::class);
        (new FeatureParametersReader())->read(new BinaryView($bytes), new Tag($tag), new LayoutReadBudget(100));
    }

    public function testSizeCannotReferToLookups(): void
    {
        $this->expectException(MalformedFontException::class);
        (new LayoutListsReader())->feature(new BinaryView(pack('nnnnnnnn', 6, 1, 0, 120, 0, 0, 0, 0)), new Tag('size'), new LayoutReadBudget(100));
    }

    public function testCharacterListBudget(): void
    {
        $this->expectException(MalformedFontException::class);
        (new FeatureParametersReader())->read(new BinaryView(pack('nnnnnnn', 0, 0, 0, 0, 0, 0, 65535)), new Tag('cv01'), new LayoutReadBudget(100));
    }

    public function testOpticalFamilyCannotSilentlyChooseOverlappingRanges(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OpticalSizeFamily('Family', [new OpticalSizeParameters(80, 1, 256, 60, 100), new OpticalSizeParameters(120, 1, 256, 90, 140)]);
    }
}
