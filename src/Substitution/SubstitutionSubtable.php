<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

interface SubstitutionSubtable
{
    public function lookupType(): int;

    public function isSupported(): bool;
}
