<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Sfnt\CollectionReader;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollectionTest extends TestCase
{
    public function testSharedTrueTypeTablesRetainAbsoluteOffsetsAndExplicitFaces(): void
    {
        $collection = (new CollectionReader())->read($this->bytes());
        self::assertSame(1, $collection->majorVersion);
        self::assertSame(2, $collection->count());
        self::assertNull($collection->signature);
        $first = $collection->font(0);
        $second = $collection->font(1);
        self::assertSame($first->record(new Tag('glyf'))->offset, $second->record(new Tag('glyf'))->offset);
        self::assertNotSame($first->record(new Tag('name'))->offset, $second->record(new Tag('name'))->offset);
        $cmap = (new UnicodeCmapReader())->read($second->table(new Tag('cmap')), $second->glyphCount());
        self::assertGreaterThan(0, $cmap->glyphFor(new CodePoint(65))->value);
    }

    public function testVersionTwoCanMixTrueTypeAndCff(): void
    {
        $collection = (new CollectionReader())->read($this->bytes('ToolkitMixed.otc'));
        self::assertSame(2, $collection->majorVersion);
        self::assertSame(SfntVersion::TrueType, $collection->font(0)->version);
        self::assertSame(SfntVersion::Cff, $collection->font(1)->version);
        self::assertTrue($collection->font(1)->hasTable(new Tag('CFF ')));
    }

    public function testSignatureBytesArePreservedWithoutImplyingVerification(): void
    {
        $bytes = $this->bytes('ToolkitMixed.otc');
        $signature = pack('Nnn', 1, 0, 0);
        $bytes = substr_replace($bytes, pack('NNN', 0x44534947, strlen($signature), strlen($bytes)), 20, 12).$signature;
        $collection = (new CollectionReader())->read($bytes);
        self::assertSame($signature, $collection->signature?->bytes(0, 8));
    }

    /** @return iterable<string, array{int, string}> */
    public static function corruptions(): iterable
    {
        yield 'zero faces' => [8, pack('N', 0)];
        yield 'hostile count' => [8, pack('N', 0xFFFFFFFF)];
        yield 'directory in header' => [12, pack('N', 8)];
        yield 'directory outside bytes' => [12, pack('N', 0xFFFFFFFC)];
        yield 'unaligned directory' => [12, pack('N', 21)];
    }

    #[DataProvider('corruptions')]
    public function testInvalidCollectionOffsetsAndCounts(int $offset, string $replacement): void
    {
        $this->expectException(MalformedFontException::class);
        (new CollectionReader())->read(substr_replace($this->bytes(), $replacement, $offset, strlen($replacement)));
    }

    public function testAggregateDirectoryBudget(): void
    {
        $this->expectException(MalformedFontException::class);
        (new CollectionReader(new ReadLimits(maxLayoutRecords: 2)))->read($this->bytes());
    }

    public function testAbsentFaceIsNotSilentlySubstituted(): void
    {
        $collection = (new CollectionReader())->read($this->bytes());
        $this->expectException(\OutOfBoundsException::class);
        $collection->font(2);
    }

    public function testSharedTableChecksumIsVerified(): void
    {
        $bytes = $this->bytes();
        $collection = (new CollectionReader())->read($bytes);
        $at = $collection->font(0)->record(new Tag('glyf'))->offset;
        $bytes[$at] = chr(ord($bytes[$at]) ^ 1);
        $this->expectException(MalformedFontException::class);
        (new CollectionReader())->read($bytes);
    }

    public function testTableCannotOverlapAnotherFacesDirectory(): void
    {
        $bytes = $this->bytes();
        $first = unpack('N', substr($bytes, 12, 4));
        $second = unpack('N', substr($bytes, 16, 4));
        self::assertIsArray($first);
        self::assertIsArray($second);
        $bytes = substr_replace($bytes, pack('N', $second[1]), $first[1] + 20, 4);
        $this->expectException(MalformedFontException::class);
        (new CollectionReader())->read($bytes);
    }

    private function bytes(string $name = 'ToolkitCollection.ttc'): string
    {
        $bytes = file_get_contents(__DIR__.'/fixtures/'.$name);
        self::assertIsString($bytes);

        return $bytes;
    }
}
