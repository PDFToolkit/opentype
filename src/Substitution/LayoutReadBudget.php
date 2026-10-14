<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;

/** @internal Per-read aggregate budget; never shared between reads. */
final class LayoutReadBudget
{
    private int $used = 0;

    public function __construct(private readonly int $maximum)
    {
    }

    public function consume(int $count): void
    {
        if ($count < 0 || $count > $this->maximum - $this->used) {
            throw new MalformedFontException('The GSUB aggregate layout-record limit was exceeded.');
        }
        $this->used += $count;
    }
}
