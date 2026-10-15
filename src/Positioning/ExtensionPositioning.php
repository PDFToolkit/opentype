<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class ExtensionPositioning implements PositioningSubtable
{
    public function __construct(public PositioningSubtable $extension)
    {
        if ($extension instanceof self || $extension->lookupType() === 9) {
            throw new \InvalidArgumentException('Extension positioning cannot extend itself.');
        }
    }

    public function lookupType(): int
    {
        return 9;
    }
}
