<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

interface PositioningSubtable
{
    public function lookupType(): int;
}
