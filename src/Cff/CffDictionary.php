<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Exception\MalformedFontException;

/** DICT syntax is preserved; hint/PrivateDICT blend programs are not executed here. */
final readonly class CffDictionary
{
    /** @param list<CffDictEntry> $entries */
    public function __construct(public array $entries)
    {
        if (!array_is_list($entries)) {
            throw new \InvalidArgumentException('DICT entries require their original order.');
        }
        foreach ($entries as $entry) {
            if (!$entry instanceof CffDictEntry) {
                throw new \InvalidArgumentException('CFF dictionaries contain immutable entries.');
            }
        }
    }

    /** @return list<float>|null */
    public function operands(int $operator): ?array
    {
        $result = null;
        foreach ($this->entries as $entry) {
            if ($entry->operator === $operator) {
                $result = $entry->operands;
            }
        }

        return $result;
    }

    public function number(int $operator, float $default = 0.0): float
    {
        $values = $this->operands($operator);
        if ($values === null) {
            return $default;
        }
        if (count($values) !== 1) {
            throw new MalformedFontException('A CFF scalar operator requires one number.');
        }

        return $values[0];
    }

    public function integer(int $operator, ?int $default = null): int
    {
        $values = $this->operands($operator);
        if ($values === null && $default !== null) {
            return $default;
        }
        if ($values === null || count($values) !== 1 || $values[0] !== floor($values[0]) || abs($values[0]) > 0x7FFFFFFF) {
            throw new MalformedFontException('A CFF structural operator requires one bounded integer.');
        }

        return (int) $values[0];
    }
}
