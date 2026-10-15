<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Names;

/** Explicit decoding capability; custom platforms never acquire a guessed encoding. */
interface NameDecoder
{
    public function decode(NameRecord $record): string;
}
