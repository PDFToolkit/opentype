<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class DeviceAdjustment
{
    /** @param list<int> $deltas */
    public function __construct(public int $startSize, public array $deltas)
    {
        if ($startSize < 0 || $startSize > 65535 || !array_is_list($deltas) || $startSize + count($deltas) > 65536) {
            throw new \InvalidArgumentException('Invalid device adjustment sizes.');
        }
    }

    public function delta(?int $pixelsPerEm): int
    {
        return $pixelsPerEm === null ? 0 : ($this->deltas[$pixelsPerEm - $this->startSize] ?? 0);
    }
}
