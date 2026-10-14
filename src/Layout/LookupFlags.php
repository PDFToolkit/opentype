<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class LookupFlags
{
    public function __construct(public int $value, public ?int $markFilteringSet = null)
    {
        if ($value < 0 || $value > 65535 || ($value & 0x00E0) !== 0) {
            throw new MalformedFontException('Invalid or reserved lookup flag bits.');
        }
        if (($value & 0x0010) !== 0 && ($markFilteringSet === null || $markFilteringSet < 0 || $markFilteringSet > 65535)) {
            throw new MalformedFontException('USE_MARK_FILTERING_SET requires a valid set index.');
        }
        if (($value & 0x0010) === 0 && $markFilteringSet !== null) {
            throw new MalformedFontException('A filtering set requires USE_MARK_FILTERING_SET.');
        }
    }
}
