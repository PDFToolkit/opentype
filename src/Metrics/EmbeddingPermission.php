<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

enum EmbeddingPermission: int
{
    case Installable = 0;
    case Restricted = 2;
    case PreviewAndPrint = 4;
    case Editable = 8;
}
