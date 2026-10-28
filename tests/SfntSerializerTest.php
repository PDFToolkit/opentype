<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Tests;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Sfnt\CollectionReader;
use PDFToolkit\OpenType\Sfnt\FontChecksum;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Sfnt\SfntSerializer;
use PDFToolkit\OpenType\Value\Tag;
use PHPUnit\Framework\TestCase;

final class SfntSerializerTest extends TestCase
{
    public function testCollectionFacesBecomeIndependentWholeFontsWithValidChecksums(): void
    {
        $collection = (new CollectionReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMixed.otc'));
        foreach ([0, 1] as $index) {
            $source = $collection->font($index);
            $bytes = (new SfntSerializer())->write($source);
            $result = (new SfntReader())->read($bytes);
            self::assertSame($source->version, $result->version);
            self::assertSame($source->glyphCount(), $result->glyphCount());
            self::assertSame(0xB1B0AFBA, (new FontChecksum())->calculate(new BinaryView($bytes)));
            foreach ($source->records() as $record) {
                $actual = $result->table($record->tag)->bytes(0, $record->length);
                $expected = $record->data()->bytes(0, $record->length);
                if ($record->tag->value === 'head') {
                    $actual = substr_replace($actual, "\0\0\0\0", 8, 4);
                    $expected = substr_replace($expected, "\0\0\0\0", 8, 4);
                }
                self::assertSame($expected, $actual, $record->tag->value);
            }
        }
    }

    public function testByteBudgetIsEnforcedBeforeCopyingTables(): void
    {
        $source = (new CollectionReader())->read(file_get_contents(__DIR__.'/fixtures/ToolkitMixed.otc'))->font(1);
        $this->expectException(\InvalidArgumentException::class);
        (new SfntSerializer(new ReadLimits(maxBytes: 64)))->write($source);
    }
}
