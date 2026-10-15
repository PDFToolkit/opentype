<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

enum PathOperator
{
    case Move;
    case Line;
    case Quadratic;
    case Cubic;
    case Close;
}
