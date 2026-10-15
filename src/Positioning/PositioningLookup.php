<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\LookupFlags;

final readonly class PositioningLookup
{
    /** @param non-empty-list<PositioningSubtable> $subtables */
    public function __construct(public int $lookupType, public LookupFlags $lookupFlags, public array $subtables)
    {
        if ($lookupType < 1 || $lookupType > 9 || $subtables === []) {
            throw new \InvalidArgumentException('Invalid positioning lookup type or empty subtable list.');
        }
        $extensionType = null;
        foreach ($subtables as $subtable) {
            if ($subtable->lookupType() !== $lookupType) {
                throw new \InvalidArgumentException('Positioning subtable and lookup types differ.');
            }
            if ($subtable instanceof ExtensionPositioning) {
                $current = $subtable->extension->lookupType();
                if ($extensionType !== null && $extensionType !== $current) {
                    throw new \InvalidArgumentException('Extension positioning subtables must share their underlying type.');
                }
                $extensionType = $current;
            }
        }
    }
}
