<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

final readonly class StylisticSetParameters implements FeatureParameters
{
    public function __construct(public int $uiLabelNameId)
    {
        if ($uiLabelNameId < 0 || $uiLabelNameId > 65535) {
            throw new \InvalidArgumentException('A stylistic-set name ID is unsigned 16-bit.');
        }
    }
}
