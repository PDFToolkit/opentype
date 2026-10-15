<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

/** Ordered raw DICT operator and numeric operands; escaped operators use 1200+code. */
final readonly class CffDictEntry
{
    /** @param list<float> $operands */
    public function __construct(public int $operator, public array $operands)
    {
        if (!array_is_list($operands)) {
            throw new \InvalidArgumentException('CFF DICT operands require order.');
        }
        foreach ($operands as $operand) {
            if ((!is_float($operand) && !is_int($operand)) || !is_finite($operand)) {
                throw new \InvalidArgumentException('CFF DICT operands must be finite numbers.');
            }
        }
    }
}
