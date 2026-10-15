<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Outline\OutlinePath;
use PDFToolkit\OpenType\Outline\OutlinePoint;
use PDFToolkit\OpenType\Outline\PathCommand;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

/** Executes unhinted Type 2 paths; no rasterization or hidden variation instance. */
final readonly class CffOutlineEngine
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function outline(CffFont $font, GlyphId $glyph, ?NormalizedCoordinates $coordinates = null, ?CffRandomSource $random = null): CffGlyphOutline
    {
        return $this->execute($font, $glyph, $coordinates, $random, [], new LayoutReadBudget($this->limits->maxOperations), new LayoutReadBudget($this->limits->maxExpandedPoints));
    }

    /** @param list<int> $ancestors */
    private function execute(CffFont $font, GlyphId $glyph, ?NormalizedCoordinates $coordinates, ?CffRandomSource $random, array $ancestors, LayoutReadBudget $operations, LayoutReadBudget $output): CffGlyphOutline
    {
        if (in_array($glyph->value, $ancestors, true) || count($ancestors) >= $this->limits->maxDepth) {
            throw new MalformedFontException('CFF composite cycle or depth budget exceeded.');
        }
        $ancestors[] = $glyph->value;
        $dictionary = $font->dictionaryFor($glyph);
        $outline = (new Type2Execution($font, $dictionary, $this->limits, $coordinates, $random, $operations, $output))->execute($font->charStrings->objects[$glyph->value]);
        $composite = $outline->composite;
        if ($composite === null) {
            return $outline;
        }
        $charset = $font->characterSet ?? throw new MalformedFontException('A Type 2 composite requires a charset.');
        $commands = [];
        foreach ([[$composite->baseCode, 0.0, 0.0], [$composite->accentCode, $composite->dx, $composite->dy]] as [$code, $dx, $dy]) {
            $sid = CffStandardData::STANDARD_ENCODING[$code];
            $component = $sid === 0 ? null : $charset->glyph($sid);
            if ($component === null) {
                throw new MalformedFontException('A Type 2 composite refers to an absent StandardEncoding glyph.');
            }
            $part = $this->execute($font, $component, $coordinates, $random, $ancestors, $operations, $output);
            foreach ($part->path->commands as $command) {
                $points = [];
                foreach ($command->points as $point) {
                    $points[] = new OutlinePoint($point->x + $dx, $point->y + $dy);
                }
                $commands[] = new PathCommand($command->operator, $points);
            }
        }

        return new CffGlyphOutline(new OutlinePath($commands), $outline->width, $outline->stemCount);
    }
}
