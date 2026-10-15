<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

final readonly class HorizontalDeviceMetrics
{
    /** @param list<HorizontalDeviceRecord> $records */
    public function __construct(public array $records)
    {
        if (!array_is_list($records)) {
            throw new \InvalidArgumentException('Device records must be ordered.');
        }
        $previous = 0;
        $glyphCount = null;
        foreach ($records as $record) {
            if (!$record instanceof HorizontalDeviceRecord || $record->pixelsPerEm <= $previous || ($glyphCount !== null && count($record->widths) !== $glyphCount)) {
                throw new \InvalidArgumentException('Device sizes must increase with a consistent glyph count.');
            }
            $previous = $record->pixelsPerEm;
            $glyphCount = count($record->widths);
        }
    }
    public function atPpem(int $pixelsPerEm): ?HorizontalDeviceRecord
    {
        if ($pixelsPerEm < 1) {
            throw new \InvalidArgumentException('A pixel size must be positive.');
        }
        foreach ($this->records as $record) {
            if ($record->pixelsPerEm === $pixelsPerEm) {
                return $record;
            }
        }
        return null;
    }
}
