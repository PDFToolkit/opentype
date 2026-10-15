<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

/** Axis value formats 1–4; linked values describe metadata, not a second match. */
final readonly class StyleValue
{
    /** @param list<StyleCoordinate> $coordinates */
    public function __construct(public StyleValueFormat $format, public int $nameId, public int $flags, public array $coordinates, public ?float $rangeMinimum = null, public ?float $rangeMaximum = null, public ?float $linkedValue = null)
    {
        if ($nameId < 0 || $nameId > 32767 || $flags < 0 || $flags > 3 || $coordinates === [] || !array_is_list($coordinates) || ($format !== StyleValueFormat::Combination && count($coordinates) !== 1)) {
            throw new \InvalidArgumentException('Invalid STAT axis-value dimensions or label.');
        }
        $previous = -1;
        foreach ($coordinates as $coordinate) {
            if (!$coordinate instanceof StyleCoordinate || $coordinate->axisIndex <= $previous) {
                throw new \InvalidArgumentException('STAT combination axes must increase without duplication.');
            }
            $previous = $coordinate->axisIndex;
        }
        if (($format === StyleValueFormat::Range) !== ($rangeMinimum !== null && $rangeMaximum !== null) || ($rangeMinimum === null) !== ($rangeMaximum === null) || ($format === StyleValueFormat::Linked) !== ($linkedValue !== null)) {
            throw new \InvalidArgumentException('STAT optional values must match their format.');
        }
        foreach ([$rangeMinimum, $rangeMaximum, $linkedValue] as $value) {
            if ($value !== null && (!is_finite($value) || $value < -32768 || $value >= 32768)) {
                throw new \InvalidArgumentException('STAT values must fit Fixed.');
            }
        }
        if ($rangeMinimum !== null && ($rangeMinimum > $coordinates[0]->value || $rangeMaximum < $coordinates[0]->value)) {
            throw new \InvalidArgumentException('STAT nominal values must lie inside their range.');
        }
    }

    public function elidable(): bool
    {
        return ($this->flags & 2) !== 0;
    }

    public function olderSibling(): bool
    {
        return ($this->flags & 1) !== 0;
    }

    /** @param array<int, float> $values Explicit coordinates indexed in STAT axis order. */
    public function matches(array $values): bool
    {
        foreach ($this->coordinates as $coordinate) {
            $value = $values[$coordinate->axisIndex] ?? null;
            if ($value === null || !is_finite($value) || ($this->format === StyleValueFormat::Range ? $value < $this->rangeMinimum || $value > $this->rangeMaximum : $value != $coordinate->value)) {
                return false;
            }
        }

        return true;
    }
}
