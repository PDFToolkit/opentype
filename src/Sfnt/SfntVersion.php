<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

enum SfntVersion: int
{
    case TrueType = 0x00010000;
    case Cff = 0x4F54544F;
}
