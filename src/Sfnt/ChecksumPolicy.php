<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

enum ChecksumPolicy
{
    case All;
    case TableChecksums;
    case Ignore;
}
