<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** Ten PANOSE classification bytes; values are descriptive, not a font-matching algorithm. */
final readonly class Panose
{
    /** @var list<int> */
    public array $values;

    /** @param list<int> $values */
    public function __construct(array $values)
    {
        if (!array_is_list($values) || count($values) !== 10) {
            throw new \InvalidArgumentException('PANOSE requires exactly ten bytes.');
        }
        foreach ($values as $value) {
            if (!$this->validByteRange($value)) {
                throw new \InvalidArgumentException('PANOSE values must fit uint8.');
            }
        }
        $this->values = $values;
    }

    private function validByteRange(mixed $value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 255;
    }
}
