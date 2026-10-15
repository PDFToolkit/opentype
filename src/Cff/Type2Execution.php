<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePath;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\PathCommand;
use PDFToolkit\OpenType\Outline\PathOperator;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

/** @internal Mutable state belongs to exactly one bounded glyph execution. */
final class Type2Execution
{
    /** @var list<float> */
    private array $stack = [];
    /** @var array<int, float> */
    private array $transient = [];
    /** @var list<PathCommand> */
    private array $commands = [];
    private float $x = 0.0;
    private float $y = 0.0;
    private bool $open = false;
    private bool $widthSeen = false;
    private ?float $width;
    private int $stems = 0;
    private bool $ended = false;
    private ?CffComposite $composite = null;
    private bool $variationSelected = false;
    private bool $blended = false;
    private int $variationIndex;
    private LayoutReadBudget $operations;
    private LayoutReadBudget $output;

    public function __construct(private readonly CffFont $font, private readonly CffFontDictionary $dictionary, private readonly OutlineLimits $limits, private readonly ?NormalizedCoordinates $coordinates, private readonly ?CffRandomSource $random, ?LayoutReadBudget $operations = null, ?LayoutReadBudget $output = null)
    {
        $this->width = $font->cff2 ? null : $dictionary->defaultWidth;
        $this->variationIndex = $dictionary->variationIndex;
        $this->operations = $operations ?? new LayoutReadBudget($limits->maxOperations);
        $this->output = $output ?? new LayoutReadBudget($limits->maxExpandedPoints);
        if ($coordinates !== null && $font->variationStore !== null && count($coordinates->values) !== $font->variationStore->axisCount) {
            throw new \InvalidArgumentException('CFF2 coordinates must match every variation-store axis.');
        }
    }

    public function execute(BinaryView $program): CffGlyphOutline
    {
        $this->run($program, [], false);
        if ((!$this->font->cff2 && !$this->ended) || $this->stack !== []) {
            throw new MalformedFontException('A charstring must terminate with a valid empty operand stack.');
        }
        $this->close();

        return new CffGlyphOutline(new OutlinePath($this->commands), $this->width, $this->stems, $this->composite);
    }

    /** @param array<string, true> $ancestors */
    private function run(BinaryView $program, array $ancestors, bool $subroutine): void
    {
        if ($program->size() > $this->limits->maxProgramBytes || count($ancestors) > min(10, $this->limits->maxDepth)) {
            throw new MalformedFontException('CFF charstring byte or call-depth budget exceeded.');
        }
        $at = 0;
        $numbers = new CffNumberReader();
        while ($at < $program->size() && !$this->ended) {
            $this->operations->consume(1);
            $byte = $program->uint8($at);
            if ($byte === 28 || $byte >= 32) {
                $this->push($numbers->read($program, $at, false));
                continue;
            }
            ++$at;
            if ($byte === 10 || $byte === 29) {
                $subrs = $byte === 10 ? $this->dictionary->localSubroutines : $this->font->globalSubroutines;
                $count = count($subrs->objects);
                $index = $this->integer($this->pop()) + ($count < 1240 ? 107 : ($count < 33900 ? 1131 : 32768));
                $key = ($byte === 10 ? 'local:' : 'global:').$index;
                if (!isset($subrs->objects[$index]) || isset($ancestors[$key])) {
                    throw new MalformedFontException('A CFF subroutine is absent or recursively cyclic.');
                }
                $next = $ancestors;
                $next[$key] = true;
                $this->run($subrs->objects[$index], $next, true);
                continue;
            }
            if ($byte === 11) {
                if (!$subroutine || $this->font->cff2) {
                    throw new MalformedFontException('return is only valid inside CFF1 subroutines.');
                }
                if ($at !== $program->size()) {
                    throw new MalformedFontException('Data follows a CFF1 subroutine return.');
                }

                return;
            }
            if ($byte === 14) {
                if ($this->font->cff2) {
                    throw new MalformedFontException('endchar is not a CFF2 operator.');
                }
                if (!$this->widthSeen && in_array(count($this->stack), [1, 5], true)) {
                    $this->width = $this->dictionary->nominalWidth + array_shift($this->stack);
                }
                $this->widthSeen = true;
                if (count($this->stack) === 4) {
                    if ($this->commands !== [] || $this->font->characterSet === null || $this->font->characterSet->cidKeyed) {
                        throw new MalformedFontException('A Type 2 endchar composite requires a name-keyed font and no preceding contours.');
                    }
                    $args = $this->take();
                    try {
                        $this->composite = new CffComposite($args[0], $args[1], $this->integer($args[2]), $this->integer($args[3]));
                    } catch (\InvalidArgumentException $error) {
                        throw new MalformedFontException('Invalid Type 2 composite.', 0, $error);
                    }
                }
                $this->arity(0);
                if ($at !== $program->size()) {
                    throw new MalformedFontException('Data follows endchar.');
                }
                $this->ended = true;
                break;
            }
            if (in_array($byte, [1, 3, 18, 23, 19, 20], true)) {
                $this->stemArguments();
                if ($byte === 19 || $byte === 20) {
                    $bytes = intdiv($this->stems + 7, 8);
                    $program->view($at, $bytes);
                    $at += $bytes;
                }
                continue;
            }
            if ($byte === 15 || $byte === 16) {
                if (!$this->font->cff2) {
                    throw new UnsupportedFontException('Legacy multiple-master operators are not OpenType CFF2 variation operators.');
                }
                if ($byte === 15) {
                    $this->arity(1);
                    if ($this->variationSelected || $this->blended) {
                        throw new MalformedFontException('vsindex may appear only once, before blend.');
                    }
                    $this->variationIndex = $this->integer($this->pop());
                    $this->variationSelected = true;
                    $this->regionScalars();
                } else {
                    $this->blend();
                }
                continue;
            }
            if ($byte === 12) {
                $escaped = $program->uint8($at++);
                if (in_array($escaped, [34, 35, 36, 37], true)) {
                    $this->flex($escaped);
                } elseif (!$this->font->cff2) {
                    $this->arithmetic($escaped);
                } else {
                    throw new UnsupportedFontException('Unknown or CFF1-only escaped CFF2 operator.');
                }
                continue;
            }
            $this->path($byte);
        }
        if (!$this->font->cff2 && !$this->ended) {
            throw new MalformedFontException('CFF1 subroutines require return and glyphs require endchar.');
        }
    }

    private function stemArguments(): void
    {
        if (!$this->widthSeen && !$this->font->cff2 && (count($this->stack) & 1) !== 0) {
            $this->width = $this->dictionary->nominalWidth + array_shift($this->stack);
        }
        $this->widthSeen = true;
        if ((count($this->stack) & 1) !== 0 || $this->stems + intdiv(count($this->stack), 2) > 96) {
            throw new MalformedFontException('Invalid CFF stem count or operand pairs.');
        }
        $this->stems += intdiv(count($this->stack), 2);
        $this->stack = [];
    }

    private function path(int $operator): void
    {
        if (in_array($operator, [4, 21, 22], true)) {
            $count = $operator === 21 ? 2 : 1;
            if (!$this->widthSeen && !$this->font->cff2 && count($this->stack) === $count + 1) {
                $this->width = $this->dictionary->nominalWidth + array_shift($this->stack);
            }
            $this->widthSeen = true;
            $this->arity($count);
            $args = $this->take();
            $this->close();
            $this->point($operator === 4 ? 0 : $args[0], $operator === 22 ? 0 : $args[$count - 1]);
            $this->append(new PathCommand(PathOperator::Move, [new OutlinePoint($this->x, $this->y)]));
            $this->open = true;

            return;
        }
        if (!$this->open) {
            throw new MalformedFontException('A CFF drawing operator requires moveto.');
        }
        $args = $this->take();
        $count = count($args);
        if ($operator === 5 && $count >= 2 && ($count % 2) === 0) {
            for ($at = 0; $at < $count; $at += 2) {
                $this->line($args[$at], $args[$at + 1]);
            }
        } elseif (($operator === 6 || $operator === 7) && $count >= 1) {
            $horizontal = $operator === 6;
            foreach ($args as $arg) {
                $this->line($horizontal ? $arg : 0, $horizontal ? 0 : $arg);
                $horizontal = !$horizontal;
            }
        } elseif ($operator === 8 && $count >= 6 && ($count % 6) === 0) {
            for ($at = 0; $at < $count; $at += 6) {
                $this->curve(array_slice($args, $at, 6));
            }
        } elseif ($operator === 24 && $count >= 8 && (($count - 2) % 6) === 0) {
            for ($at = 0; $at < $count - 2; $at += 6) {
                $this->curve(array_slice($args, $at, 6));
            }
            $this->line($args[$count - 2], $args[$count - 1]);
        } elseif ($operator === 25 && $count >= 8 && (($count - 6) % 2) === 0) {
            for ($at = 0; $at < $count - 6; $at += 2) {
                $this->line($args[$at], $args[$at + 1]);
            }
            $this->curve(array_slice($args, -6));
        } elseif (($operator === 26 || $operator === 27) && $count >= 4 && in_array($count % 4, [0, 1], true)) {
            $at = 0;
            $optional = $count % 4 === 1 ? $args[$at++] : 0.0;
            while ($at < $count) {
                $this->curve($operator === 26 ? [$optional, $args[$at], $args[$at + 1], $args[$at + 2], 0, $args[$at + 3]] : [$args[$at], $optional, $args[$at + 1], $args[$at + 2], $args[$at + 3], 0]);
                $optional = 0.0;
                $at += 4;
            }
        } elseif (($operator === 30 || $operator === 31) && $count >= 4 && in_array($count % 4, [0, 1], true)) {
            $horizontal = $operator === 31;
            $at = 0;
            while ($count - $at >= 4) {
                $extra = $count - $at === 5 ? $args[$at + 4] : 0.0;
                $this->curve($horizontal ? [$args[$at], 0, $args[$at + 1], $args[$at + 2], $extra, $args[$at + 3]] : [0, $args[$at], $args[$at + 1], $args[$at + 2], $args[$at + 3], $extra]);
                $at += $count - $at === 5 ? 5 : 4;
                $horizontal = !$horizontal;
            }
        } else {
            throw new MalformedFontException('Unknown CFF path operator or invalid operand count.');
        }
    }

    private function flex(int $operator): void
    {
        $this->arity(match ($operator) {
            34 => 7, 35 => 13, 36 => 9, default => 11
        });
        if (!$this->open) {
            throw new MalformedFontException('A CFF flex requires moveto.');
        }
        $a = $this->take();
        if ($operator === 35) {
            $this->curve(array_slice($a, 0, 6));
            $this->curve(array_slice($a, 6, 6));
        } elseif ($operator === 34) {
            $this->curve([$a[0], 0, $a[1], $a[2], $a[3], 0]);
            $this->curve([$a[4], 0, $a[5], -$a[2], $a[6], 0]);
        } elseif ($operator === 36) {
            $this->curve([$a[0], $a[1], $a[2], $a[3], $a[4], 0]);
            $this->curve([$a[5], 0, $a[6], $a[7], $a[8], -($a[1] + $a[3] + $a[7])]);
        } else {
            $dx = $a[0] + $a[2] + $a[4] + $a[6] + $a[8];
            $dy = $a[1] + $a[3] + $a[5] + $a[7] + $a[9];
            $this->curve(array_slice($a, 0, 6));
            $this->curve([$a[6], $a[7], $a[8], $a[9], abs($dx) > abs($dy) ? $a[10] : -$dx, abs($dx) > abs($dy) ? -$dy : $a[10]]);
        }
    }

    private function arithmetic(int $operator): void
    {
        if ($operator === 0) {
            // Deprecated dotsection is defined as a no-op by Adobe Type 2 Appendix C.
            return;
        }
        if (in_array($operator, [3, 4, 10, 11, 12, 15, 24], true)) {
            $b = $this->pop();
            $a = $this->pop();
            $this->push(match ($operator) {
                3 => (float) ($a != 0 && $b != 0), 4 => (float) ($a != 0 || $b != 0),
                10 => $a + $b, 11 => $a - $b,
                12 => $b == 0 ? throw new MalformedFontException('CFF division by zero.') : $a / $b,
                15 => (float) ($a === $b), default => $a * $b,
            });
        } elseif (in_array($operator, [5, 9, 14, 26], true)) {
            $value = $this->pop();
            $this->push(match ($operator) {
                5 => (float) ($value == 0), 9 => abs($value), 14 => -$value, default => $value < 0 ? throw new MalformedFontException('CFF square root of a negative value.') : sqrt($value)
            });
        } elseif ($operator === 18) {
            $this->pop();
        } elseif ($operator === 20) {
            $index = $this->integer($this->pop());
            $value = $this->pop();
            if ($index < 0 || $index >= 32) {
                throw new MalformedFontException('CFF transient storage index is out of range.');
            }
            $this->transient[$index] = $value;
        } elseif ($operator === 21) {
            $index = $this->integer($this->pop());
            $this->push($this->transient[$index] ?? throw new MalformedFontException('CFF transient storage was not initialized.'));
        } elseif ($operator === 22) {
            $v2 = $this->pop();
            $v1 = $this->pop();
            $s2 = $this->pop();
            $s1 = $this->pop();
            $this->push($v1 <= $v2 ? $s1 : $s2);
        } elseif ($operator === 23) {
            $value = $this->random?->value() ?? throw new UnsupportedFontException('The CFF random operator requires an explicit random source.');
            if (!is_finite($value) || $value <= 0 || $value > 1) {
                throw new \InvalidArgumentException('The CFF random source must return a value in (0, 1].');
            }
            $this->push($value);
        } elseif ($operator === 27) {
            $value = $this->pop();
            $this->push($value);
            $this->push($value);
        } elseif ($operator === 28) {
            $b = $this->pop();
            $a = $this->pop();
            $this->push($b);
            $this->push($a);
        } elseif ($operator === 29) {
            $index = max(0, $this->integer($this->pop()));
            $this->push($this->stack[count($this->stack) - 1 - $index] ?? throw new MalformedFontException('CFF index exceeds the operand stack.'));
        } elseif ($operator === 30) {
            $j = $this->integer($this->pop());
            $n = $this->integer($this->pop());
            if ($n < 0 || $n > count($this->stack)) {
                throw new MalformedFontException('CFF roll exceeds the operand stack.');
            }
            if ($n > 0) {
                $j = (($j % $n) + $n) % $n;
                if ($j !== 0) {
                    $tail = array_splice($this->stack, -$n);
                    $this->stack = array_merge($this->stack, array_slice($tail, -$j), array_slice($tail, 0, $n - $j));
                }
            }
        } else {
            throw new UnsupportedFontException('Unknown escaped CFF1 operator.');
        }
    }

    /** @return list<float> */
    private function regionScalars(): array
    {
        $store = $this->font->variationStore ?? throw new MalformedFontException('CFF2 variation requires a VariationStore.');
        $coordinates = $this->coordinates ?? throw new UnsupportedFontException('CFF2 blend requires explicit normalized coordinates.');
        $data = $store->data[$this->variationIndex] ?? throw new MalformedFontException('CFF2 vsindex selects absent variation data.');
        $scalars = [];
        foreach ($data->regionIndices as $region) {
            $scalars[] = $store->regions[$region]->scalar($coordinates);
        }

        return $scalars;
    }

    private function blend(): void
    {
        $count = $this->integer($this->pop());
        $scalars = $this->regionScalars();
        $regions = count($scalars);
        if ($count < 1 || $count > intdiv(count($this->stack), 1 + $regions)) {
            throw new MalformedFontException('CFF2 blend operands exceed the available stack.');
        }
        $values = array_splice($this->stack, -$count * (1 + $regions));
        for ($index = 0; $index < $count; ++$index) {
            $value = $values[$index];
            foreach ($scalars as $region => $scalar) {
                $value += $values[$count + $index * $regions + $region] * $scalar;
            }
            $this->push($value);
        }
        $this->blended = true;
    }

    private function point(float $dx, float $dy): void
    {
        $this->x += $dx;
        $this->y += $dy;
        if (!is_finite($this->x) || !is_finite($this->y)) {
            throw new MalformedFontException('CFF coordinate arithmetic overflowed.');
        }
    }

    private function line(float $dx, float $dy): void
    {
        $this->point($dx, $dy);
        $this->append(new PathCommand(PathOperator::Line, [new OutlinePoint($this->x, $this->y)]));
    }

    /** @param list<float|int> $values */
    private function curve(array $values): void
    {
        $points = [];
        for ($index = 0; $index < 6; $index += 2) {
            $this->point($values[$index], $values[$index + 1]);
            $points[] = new OutlinePoint($this->x, $this->y);
        }
        $this->append(new PathCommand(PathOperator::Cubic, $points));
    }

    private function close(): void
    {
        if ($this->open) {
            $this->append(new PathCommand(PathOperator::Close));
            $this->open = false;
        }
    }

    private function append(PathCommand $command): void
    {
        $this->output->consume(max(1, count($command->points)));
        $this->commands[] = $command;
    }

    private function push(float $value): void
    {
        if (!is_finite($value) || count($this->stack) >= ($this->font->cff2 ? 513 : 48)) {
            throw new MalformedFontException('CFF operand stack or numeric bounds exceeded.');
        }
        $this->stack[] = $value;
    }

    private function pop(): float
    {
        return array_pop($this->stack) ?? throw new MalformedFontException('CFF operand stack underflow.');
    }

    /** @return list<float> */
    private function take(): array
    {
        $stack = $this->stack;
        $this->stack = [];

        return $stack;
    }

    private function arity(int $count): void
    {
        if (count($this->stack) !== $count) {
            throw new MalformedFontException('Invalid CFF operand count.');
        }
    }

    private function integer(float $value): int
    {
        if ($value !== floor($value) || $value < -2147483648 || $value > 2147483647) {
            throw new MalformedFontException('A CFF index requires a bounded integer operand.');
        }

        return (int) $value;
    }
}
