<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

/** Per-sequence identity: null component denotes the ligature, an index denotes an attached glyph. */
final readonly class LigatureAssociation
{
    public function __construct(public int $ligatureId, public ?int $componentIndex = null)
    {
        if ($ligatureId < 1 || $ligatureId > 1073741824 || ($componentIndex !== null && ($componentIndex < 0 || $componentIndex > 1048575))) {
            throw new \InvalidArgumentException('Invalid ligature association identity or component.');
        }
    }
}
