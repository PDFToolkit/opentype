<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\Tag;

/** Directory model; preserved outline bytes are not an outline interpreter. */
final readonly class SfntFont
{
    /** @var array<string, TableRecord> */
    private array $tables;

    /** @param list<TableRecord> $records */
    public function __construct(public SfntVersion $version, array $records, private int $numberOfGlyphs)
    {
        if ($numberOfGlyphs < 1 || $numberOfGlyphs > 65535 || $records === [] || !array_is_list($records)) {
            throw new \InvalidArgumentException('A font requires a non-empty directory and a bounded non-zero glyph count.');
        }

        $tables = [];
        foreach ($records as $record) {
            if (!$record instanceof TableRecord) {
                throw new \InvalidArgumentException('A font directory contains immutable table records.');
            }
            if (isset($tables[$record->tag->value])) {
                throw new \InvalidArgumentException('A font directory cannot contain duplicate tags.');
            }
            $tables[$record->tag->value] = $record;
        }
        $this->tables = $tables;
    }

    public function hasTable(Tag $tag): bool
    {
        return isset($this->tables[$tag->value]);
    }

    public function table(Tag $tag): BinaryView
    {
        return $this->record($tag)->data();
    }

    public function record(Tag $tag): TableRecord
    {
        return $this->tables[$tag->value] ?? throw new MalformedFontException('Missing OpenType table: '.$tag->value);
    }

    /** @return list<TableRecord> */
    public function records(): array
    {
        return array_values($this->tables);
    }

    public function glyphCount(): int
    {
        return $this->numberOfGlyphs;
    }
}
