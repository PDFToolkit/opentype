<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

final readonly class GaspRange
{
    public function __construct(public int $maximumPpem, public int $behavior)
    {
        if ($maximumPpem < 0 || $maximumPpem > 65535 || $behavior < 0 || $behavior > 15) {
            throw new \InvalidArgumentException('Invalid gasp range.');
        }
    }

    public function gridFit(): bool
    {
        return ($this->behavior & 1) !== 0;
    }
    public function grayscale(): bool
    {
        return ($this->behavior & 2) !== 0;
    }
    public function symmetricGridFit(): bool
    {
        return ($this->behavior & 4) !== 0;
    }
    public function symmetricSmoothing(): bool
    {
        return ($this->behavior & 8) !== 0;
    }
}
