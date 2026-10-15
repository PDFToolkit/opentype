<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class EntryExitRecord
{
    public function __construct(public ?Anchor $entryAnchor, public ?Anchor $exitAnchor)
    {
    }
}
