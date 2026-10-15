<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Outline\OutlinePath;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\PathCommand;

/** Explicit affine conversion from charstring coordinates to em coordinates. */
final readonly class CffFontMatrix
{
    public function __construct(public float $a = 0.001, public float $b = 0.0, public float $c = 0.0, public float $d = 0.001, public float $tx = 0.0, public float $ty = 0.0)
    {
        foreach ([$a, $b, $c, $d, $tx, $ty] as $number) {
            if (!is_finite($number)) {
                throw new \InvalidArgumentException('CFF matrix values must be finite.');
            }
        }
        if ($a * $d - $b * $c == 0.0) {
            throw new \InvalidArgumentException('A CFF font matrix must be invertible.');
        }
    }

    /** Apply the inner transform first, then this matrix. */
    public function compose(self $inner): self
    {
        return new self($this->a * $inner->a + $this->c * $inner->b, $this->b * $inner->a + $this->d * $inner->b, $this->a * $inner->c + $this->c * $inner->d, $this->b * $inner->c + $this->d * $inner->d, $this->a * $inner->tx + $this->c * $inner->ty + $this->tx, $this->b * $inner->tx + $this->d * $inner->ty + $this->ty);
    }

    public function transform(OutlinePath $path, float $scale = 1.0): OutlinePath
    {
        if (!is_finite($scale) || $scale <= 0) {
            throw new \InvalidArgumentException('CFF output scale must be finite and positive.');
        }
        $commands = [];
        foreach ($path->commands as $command) {
            $points = [];
            foreach ($command->points as $point) {
                $points[] = new OutlinePoint(($this->a * $point->x + $this->c * $point->y + $this->tx) * $scale, ($this->b * $point->x + $this->d * $point->y + $this->ty) * $scale);
            }
            $commands[] = new PathCommand($command->operator, $points);
        }

        return new OutlinePath($commands);
    }
}
