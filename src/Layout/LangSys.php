<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class LangSys
{
    /** @var list<int> */
    public array $featureIndices;

    /** @param list<int> $featureIndices */
    public function __construct(public ?int $requiredFeatureIndex, array $featureIndices)
    {
        if ($requiredFeatureIndex !== null && ($requiredFeatureIndex < 0 || $requiredFeatureIndex > 65534)) {
            throw new MalformedFontException('Invalid required feature index.');
        }
        foreach ($featureIndices as $index) {
            if (!is_int($index) || $index < 0 || $index > 65535) {
                throw new MalformedFontException('Invalid feature index.');
            }
        }
        $this->featureIndices = array_values($featureIndices);
    }
}
