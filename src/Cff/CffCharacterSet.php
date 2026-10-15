<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CffCharacterSet
{
    /** @param list<int> $identifiers SID for name-keyed fonts, CID otherwise. */
    public function __construct(public bool $cidKeyed, public array $identifiers, public CffIndex $strings)
    {
        if (!array_is_list($identifiers) || $identifiers === [] || $identifiers[0] !== 0 || count(array_unique($identifiers)) !== count($identifiers)) {
            throw new \InvalidArgumentException('A CFF charset needs unique ordered identifiers beginning with .notdef.');
        }
        foreach ($identifiers as $identifier) {
            if ($identifier < 0 || $identifier > 65535 || (!$cidKeyed && $identifier >= 391 + count($strings->objects))) {
                throw new \InvalidArgumentException('A CFF charset identifier is out of range.');
            }
        }
    }

    public function glyph(int $identifier): ?GlyphId
    {
        $index = array_search($identifier, $this->identifiers, true);

        return $index === false ? null : new GlyphId($index);
    }

    public function name(GlyphId $glyph): ?string
    {
        $identifier = $this->identifiers[$glyph->value] ?? throw new \OutOfBoundsException('A CFF glyph exceeds its charset.');
        if ($this->cidKeyed) {
            return null;
        }
        if ($identifier < 391) {
            return CffStandardData::STRINGS[$identifier];
        }
        $string = $this->strings->objects[$identifier - 391];

        return $string->bytes(0, $string->size());
    }
}
