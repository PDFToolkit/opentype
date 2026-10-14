<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class ExtensionSubstFormat1 implements SubstitutionSubtable
{
    public function __construct(public int $extensionLookupType, public SubstitutionSubtable $extension)
    {
        if ($extensionLookupType === 7 || $extensionLookupType < 1 || $extensionLookupType > 8 || $extension->lookupType() !== $extensionLookupType) {
            throw new MalformedFontException('Invalid extension lookup type.');
        }
    }

    public function lookupType(): int
    {
        return 7;
    }

    public function isSupported(): bool
    {
        return $this->extension->isSupported();
    }
}
