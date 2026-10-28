<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

/** Declared CID registry, ordering and supplement, without external collection lookup. */
final readonly class CffRos
{
    public function __construct(public string $registry, public string $ordering, public int $supplement)
    {
        if ($registry === '' || $ordering === '' || $supplement < 0 || $supplement > 0x7FFFFFFF) {
            throw new \InvalidArgumentException('Invalid CFF CID collection identity.');
        }
    }
}
