<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Complete fvar records; the default instance remains available with zero records. */
final readonly class FvarTable
{
    /**
     * @param list<VariationAxis> $axes
     * @param list<NamedInstance> $instances
     */
    public function __construct(public array $axes, public array $instances)
    {
        if (!array_is_list($axes) || !array_is_list($instances)) {
            throw new \InvalidArgumentException('Variation records must preserve font order.');
        }
        $tags = [];
        foreach ($axes as $axis) {
            if (!$axis instanceof VariationAxis || isset($tags[$axis->tag->value])) {
                throw new \InvalidArgumentException('Variation axes require unique tags.');
            }
            $tags[$axis->tag->value] = true;
        }
        foreach ($instances as $instance) {
            if (!$instance instanceof NamedInstance || count($instance->coordinates) !== count($axes)) {
                throw new \InvalidArgumentException('Every named instance requires every axis.');
            }
            foreach ($axes as $index => $axis) {
                if ($instance->coordinates[$index] < $axis->minimum || $instance->coordinates[$index] > $axis->maximum) {
                    throw new \InvalidArgumentException('A named instance exceeds its axis range.');
                }
            }
        }
    }

    public function normalize(UserCoordinates $coordinates, ?AvarTable $avar = null): NormalizedCoordinates
    {
        $known = [];
        foreach ($this->axes as $axis) {
            $known[$axis->tag->value] = true;
        }
        foreach ($coordinates->values as $tag => $value) {
            if (!isset($known[$tag])) {
                throw new \InvalidArgumentException('Unknown user variation axis: '.$tag);
            }
        }
        if ($avar !== null && count($avar->segments) !== count($this->axes)) {
            throw new \InvalidArgumentException('fvar and avar must have the same axis count.');
        }
        $normalized = [];
        foreach ($this->axes as $index => $axis) {
            $fixed = $axis->normalize($coordinates->values[$axis->tag->value] ?? $axis->default);
            $fixed = $avar === null ? $fixed : $avar->segments[$index]->map($fixed);
            $normalized[] = $fixed / 65536.0;
        }

        return new NormalizedCoordinates($normalized);
    }

    public function instanceCoordinates(int $index): UserCoordinates
    {
        $instance = $this->instances[$index] ?? throw new \OutOfBoundsException('Unknown named instance.');
        $values = [];
        foreach ($this->axes as $axisIndex => $axis) {
            $values[$axis->tag->value] = $instance->coordinates[$axisIndex];
        }

        return new UserCoordinates($values);
    }
}
