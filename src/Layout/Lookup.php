<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\ExtensionSubstFormat1;
use PDFToolkit\OpenType\Substitution\SubstitutionSubtable;

final readonly class Lookup
{
    /** @var non-empty-list<SubstitutionSubtable> */
    public array $subtables;

    /** @param non-empty-list<SubstitutionSubtable> $subtables */
    public function __construct(public int $lookupType, public LookupFlags $lookupFlags, array $subtables)
    {
        if ($lookupType < 1 || $lookupType > 8 || $subtables === []) {
            throw new MalformedFontException('Invalid GSUB lookup type or empty subtable list.');
        }
        $extensionType = null;
        foreach ($subtables as $subtable) {
            if (!$subtable instanceof SubstitutionSubtable) {
                throw new \InvalidArgumentException('Lookup subtables must implement SubstitutionSubtable.');
            }

            if ($subtable->lookupType() !== $lookupType) {
                throw new MalformedFontException('Subtable type differs from lookup type.');
            }
            if ($subtable instanceof ExtensionSubstFormat1) {
                $currentType = $subtable->extensionLookupType;
                if ($extensionType !== null && $extensionType !== $currentType) {
                    throw new MalformedFontException('Extension subtables must have the same underlying lookup type.');
                }
                $extensionType = $currentType;
            }
        }
        $this->subtables = array_values($subtables);
    }
}
