<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** Ordered uint32 bit fields. A set bit is a font declaration, not proven glyph coverage. */
final readonly class MetricRanges
{
    /** @var list<int> */
    public array $words;

    /** @param list<int> $words */
    public function __construct(array $words)
    {
        if (!array_is_list($words) || !in_array(count($words), [2, 4], true)) {
            throw new \InvalidArgumentException('Metric coverage ranges require two or four words.');
        }
        foreach ($words as $word) {
            if (!$this->validByteRange($word)) {
                throw new \InvalidArgumentException('Metric range words must fit uint32.');
            }
        }
        $this->words = $words;
    }

    public function containsBit(int $bit): bool
    {
        if ($bit < 0 || $bit >= 32 * count($this->words)) {
            throw new \OutOfBoundsException('A range bit exceeds its field.');
        }

        return ($this->words[intdiv($bit, 32)] & (1 << ($bit % 32))) !== 0;
    }

    private function validByteRange(mixed $value): bool
    {
        return is_int($value) && $value >= 0 && $value <= 0xFFFFFFFF;
    }
}
