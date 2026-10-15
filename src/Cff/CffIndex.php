<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;

final readonly class CffIndex
{
    /** @param list<BinaryView> $objects */
    public function __construct(public array $objects, public int $endOffset)
    {
        if (!array_is_list($objects) || $endOffset < 0) {
            throw new \InvalidArgumentException('A CFF INDEX requires ordered bounded views.');
        }
        foreach ($objects as $object) {
            if (!$object instanceof BinaryView) {
                throw new \InvalidArgumentException('CFF INDEX entries must be bounded views.');
            }
        }
    }
}
