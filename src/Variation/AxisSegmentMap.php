<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class AxisSegmentMap
{
    /** @param list<AxisValueMap> $mappings */
    public function __construct(public array $mappings)
    {
        if (!array_is_list($mappings)) {
            throw new \InvalidArgumentException('Axis mappings require font order.');
        }
        $previous = null;
        foreach ($mappings as $mapping) {
            if (!$mapping instanceof AxisValueMap || ($previous !== null && ($mapping->fromCoordinate <= $previous->fromCoordinate || $mapping->toCoordinate < $previous->toCoordinate))) {
                throw new \InvalidArgumentException('Axis mappings must increase with unique source coordinates.');
            }
            $previous = $mapping;
        }
    }

    public function active(): bool
    {
        $required = [-16384 => false, 0 => false, 16384 => false];
        foreach ($this->mappings as $mapping) {
            if (array_key_exists($mapping->fromCoordinate, $required) && $mapping->fromCoordinate === $mapping->toCoordinate) {
                $required[$mapping->fromCoordinate] = true;
            }
        }

        return !in_array(false, $required, true);
    }

    /** Map a 16.16 normalized coordinate. Missing mandatory anchors disable this map. */
    public function map(int $coordinate): int
    {
        if ($coordinate < -65536 || $coordinate > 65536) {
            throw new \InvalidArgumentException('Axis normalization requires a normalized 16.16 coordinate.');
        }
        if (!$this->active()) {
            return $coordinate;
        }
        $previous = null;
        foreach ($this->mappings as $mapping) {
            $from = $mapping->fromCoordinate * 4;
            $to = $mapping->toCoordinate * 4;
            if ($from === $coordinate) {
                return $to;
            }
            if ($from > $coordinate && $previous !== null) {
                $ratio = (int) floor(($coordinate - $previous->fromCoordinate * 4) * 65536 / ($from - $previous->fromCoordinate * 4) + 0.5);
                $value = $previous->toCoordinate * 4 + (int) floor($ratio * ($to - $previous->toCoordinate * 4) / 65536 + 0.5);

                return max(-65536, min(65536, $value));
            }
            $previous = $mapping;
        }

        return $coordinate;
    }
}
