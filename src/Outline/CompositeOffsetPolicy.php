<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Explicit interpretation when neither component offset-scaling flag is set. */
enum CompositeOffsetPolicy
{
    case Unscaled;
    case Scaled;
    case RejectAmbiguous;
}
