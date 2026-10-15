<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\Coverage;

final readonly class MarkAttachment implements PositioningSubtable
{
    /** @param list<MarkRecord> $markRecords
     * @param list<AttachmentAnchors> $targetAnchors
     */
    public function __construct(public int $type, public Coverage $markCoverage, public Coverage $targetCoverage, public int $markClassCount, public array $markRecords, public array $targetAnchors)
    {
        if (!in_array($type, [4, 5, 6], true) || $markClassCount < 1 || count($markRecords) !== $markCoverage->count() || count($targetAnchors) !== $targetCoverage->count()) {
            throw new \InvalidArgumentException('Invalid mark attachment dimensions.');
        }
        foreach ($markRecords as $record) {
            if ($record->markClass >= $markClassCount) {
                throw new \InvalidArgumentException('A mark class exceeds the attachment matrix.');
            }
        }
        foreach ($targetAnchors as $target) {
            foreach ($target->components as $component) {
                if (count($component) !== $markClassCount) {
                    throw new \InvalidArgumentException('Attachment rows must match the class count.');
                }
            }
        }
    }

    public function lookupType(): int
    {
        return $this->type;
    }
}
