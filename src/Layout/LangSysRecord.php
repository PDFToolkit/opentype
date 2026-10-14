<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\Tag;

final readonly class LangSysRecord
{
    public function __construct(public Tag $langSysTag, public LangSys $langSys)
    {
    }
}
