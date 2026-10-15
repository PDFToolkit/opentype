<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Layout\Feature;

final readonly class FeatureVariationRecord
{
    /** @param list<FeatureCondition> $conditions
     * @param array<int, Feature> $substitutions
     */
    public function __construct(public array $conditions, public array $substitutions, public bool $supportedSubstitutions = true)
    {
        if (!array_is_list($conditions)) {
            throw new \InvalidArgumentException('Feature conditions must be ordered.');
        }
        foreach ($conditions as $condition) {
            if (!$condition instanceof FeatureCondition) {
                throw new \InvalidArgumentException('Feature variations require FeatureCondition objects.');
            }
        }
        $previous = -1;
        foreach ($substitutions as $index => $feature) {
            if (!is_int($index) || $index < 0 || $index > 65535 || $index <= $previous || !$feature instanceof Feature) {
                throw new \InvalidArgumentException('Feature substitutions must have ordered unique feature indices.');
            }
            $previous = $index;
        }
    }

    public function matches(NormalizedCoordinates $coordinates): bool
    {
        if (!$this->supportedSubstitutions) {
            return false;
        }
        foreach ($this->conditions as $condition) {
            if (!$condition->matches($coordinates)) {
                return false;
            }
        }
        return true;
    }
}
