<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class UnsupportedSubstitution implements SubstitutionSubtable
{
    public function __construct(public int $type, public int $format, public BinaryView $source)
    {
        if (!in_array($type, [5, 6, 8], true) || $format < 1 || $format > ($type === 8 ? 1 : 3)) {
            throw new MalformedFontException('Invalid unsupported substitution type or format.');
        }
    }

    public function lookupType(): int
    {
        return $this->type;
    }

    public function isSupported(): bool
    {
        return false;
    }
}
