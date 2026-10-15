<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** Reads the GDEF classifications used by layout filtering, without implicit font access. */
final readonly class GdefReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $glyphCount): GdefTable
    {
        if ($table->size() > $this->limits->maxBytes || $glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('GDEF exceeds the configured byte or glyph budget.');
        }
        $minor = $table->uint16(2);
        if ($table->uint16(0) !== 1 || !in_array($minor, [0, 2, 3], true)) {
            throw new UnsupportedFontException('Unsupported GDEF version.');
        }
        $header = $minor === 0 ? 12 : ($minor === 2 ? 14 : 18);
        $table->bytes(0, $header);
        $reader = new LayoutBinaryReader();
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $glyphClasses = $table->uint16(4) === 0 ? new ClassDefinition() : $reader->classes($reader->child($table, $table->uint16(4), $header), $glyphCount, $budget);
        $markClasses = $table->uint16(10) === 0 ? new ClassDefinition() : $reader->classes($reader->child($table, $table->uint16(10), $header), $glyphCount, $budget);
        $sets = [];
        if ($minor >= 2 && $table->uint16(12) !== 0) {
            $markSets = $reader->child($table, $table->uint16(12), $header);
            if ($markSets->uint16(0) !== 1) {
                throw new MalformedFontException('Unknown MarkGlyphSets format.');
            }
            $count = $markSets->uint16(2);
            $reader->records($markSets, 4, $count, 4, $budget);
            for ($i = 0; $i < $count; ++$i) {
                $sets[] = $reader->coverage($reader->child($markSets, $markSets->uint32(4 + 4 * $i), 4 + 4 * $count), $glyphCount, $budget);
            }
        }
        // Optional attachment, caret and variation tables are outside classification execution.
        // Their presence remains observable through the source SFNT table.
        return new GdefTable($glyphClasses, $markClasses, $sets);
    }
}
