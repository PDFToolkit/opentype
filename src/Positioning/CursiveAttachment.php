<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\Coverage;

final readonly class CursiveAttachment implements PositioningSubtable
{
    /** @param list<EntryExitRecord> $entryExitRecords */
    public function __construct(public Coverage $coverage, public array $entryExitRecords)
    {
        if (!array_is_list($entryExitRecords) || count($entryExitRecords) !== $coverage->count()) {
            throw new \InvalidArgumentException('Cursive anchor records must match coverage.');
        }
        foreach ($entryExitRecords as $record) {
            if (!$record instanceof EntryExitRecord) {
                throw new \InvalidArgumentException('Cursive attachments require EntryExitRecord objects.');
            }
        }
    }

    public function lookupType(): int
    {
        return 3;
    }
}
