<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\Tag;

final readonly class ScriptRecord
{
    public function __construct(public Tag $scriptTag, public Script $script)
    {
        if ($scriptTag->value === 'DFLT' && $script->defaultLangSys === null) {
            throw new MalformedFontException('The DFLT script requires a default language system.');
        }
    }
}
