<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\ContextRule;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class ContextPositioning implements PositioningSubtable
{
    /** @param list<ContextRule> $rules */
    public function __construct(public int $type, public int $format, public Coverage $coverage, public array $rules)
    {
        if (!in_array($type, [7, 8], true) || $format < 1 || $format > 3 || !array_is_list($rules)) {
            throw new \InvalidArgumentException('Invalid contextual positioning type or format.');
        }
        foreach ($rules as $rule) {
            if (!$rule instanceof ContextRule) {
                throw new \InvalidArgumentException('Contextual positioning requires ContextRule objects.');
            }
        }
    }

    public function lookupType(): int
    {
        return $this->type;
    }
}
