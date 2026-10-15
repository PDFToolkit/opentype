<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

enum StyleValueFormat: int
{
    case Point = 1;
    case Range = 2;
    case Linked = 3;
    case Combination = 4;
}
