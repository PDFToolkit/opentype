<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\Tag;

final readonly class Script
{
    /** @var list<LangSysRecord> */
    public array $langSysRecords;

    /** @param list<LangSysRecord> $langSysRecords */
    public function __construct(public ?LangSys $defaultLangSys, array $langSysRecords = [])
    {
        foreach ($langSysRecords as $record) {
            if (!$record instanceof LangSysRecord) {
                throw new \InvalidArgumentException('Every collection element must be a LangSysRecord object.');
            }
        }

        $previous = null;
        foreach ($langSysRecords as $record) {
            if ($previous !== null && strcmp($previous, $record->langSysTag->value) >= 0) {
                throw new MalformedFontException('Language tags must be strictly ordered.');
            }
            $previous = $record->langSysTag->value;
        }
        $this->langSysRecords = array_values($langSysRecords);
    }

    public function language(Tag $tag): ?LangSys
    {
        foreach ($this->langSysRecords as $record) {
            if ($record->langSysTag->value === $tag->value) {
                return $record->langSys;
            }
        }

        return null;
    }
}
