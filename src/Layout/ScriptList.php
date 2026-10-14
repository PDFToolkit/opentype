<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\Tag;

final readonly class ScriptList
{
    /** @var list<ScriptRecord> */
    public array $scriptRecords;

    /** @param list<ScriptRecord> $scriptRecords */
    public function __construct(array $scriptRecords)
    {
        foreach ($scriptRecords as $record) {
            if (!$record instanceof ScriptRecord) {
                throw new \InvalidArgumentException('Every collection element must be a ScriptRecord object.');
            }
        }

        $previous = null;
        foreach ($scriptRecords as $record) {
            if ($previous !== null && strcmp($previous, $record->scriptTag->value) >= 0) {
                throw new MalformedFontException('Script tags must be strictly ordered.');
            }
            $previous = $record->scriptTag->value;
        }
        $this->scriptRecords = array_values($scriptRecords);
    }

    public function script(Tag $tag): ?Script
    {
        foreach ($this->scriptRecords as $record) {
            if ($record->scriptTag->value === $tag->value) {
                return $record->script;
            }
        }

        return null;
    }
}
