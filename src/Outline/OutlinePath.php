<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class OutlinePath
{
    /** @param list<PathCommand> $commands */
    public function __construct(public array $commands)
    {
        if (!array_is_list($commands)) {
            throw new \InvalidArgumentException('Outline path commands require drawing order.');
        }
        $open = false;
        foreach ($commands as $command) {
            if (!$command instanceof PathCommand) {
                throw new \InvalidArgumentException('Outline paths require immutable commands.');
            }
            if ($command->operator === PathOperator::Move) {
                if ($open) {
                    throw new \InvalidArgumentException('Close the preceding contour before moving.');
                }
                $open = true;
            } elseif (!$open) {
                throw new \InvalidArgumentException('Drawing requires an open contour.');
            } elseif ($command->operator === PathOperator::Close) {
                $open = false;
            }
        }
        if ($open) {
            throw new \InvalidArgumentException('Font outline contours must be closed.');
        }
    }
}
