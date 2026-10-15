<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Separate parsing and expansion budgets; component graphs can expand exponentially. */
final readonly class OutlineLimits
{
    public function __construct(public int $maxBytes = 67108864, public int $maxRecords = 262144, public int $maxProgramBytes = 1048576, public int $maxDepth = 32, public int $maxExpandedPoints = 262144, public int $maxOperations = 1048576)
    {
        foreach ([$maxBytes, $maxRecords, $maxProgramBytes, $maxDepth, $maxExpandedPoints, $maxOperations] as $value) {
            if ($value < 1 || $value > 1073741824) {
                throw new \InvalidArgumentException('Outline budgets must be positive and bounded.');
            }
        }
        if ($maxDepth > 256) {
            throw new \InvalidArgumentException('Outline nesting is bounded to 256 levels.');
        }
    }
}
