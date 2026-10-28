<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Sfnt;

use PDFToolkit\OpenType\Binary\BinaryView;

/** Selected faces retain absolute collection offsets. Signature bytes are not verified. */
final readonly class SfntCollection
{
    /** @param non-empty-list<SfntFont> $fonts */
    public function __construct(public int $majorVersion, private array $fonts, public ?BinaryView $signature = null)
    {
        if (!in_array($majorVersion, [1, 2], true) || !array_is_list($fonts) || $fonts === [] || ($majorVersion === 1 && $signature !== null)) {
            throw new \InvalidArgumentException('Invalid collection version or face list.');
        }
        foreach ($fonts as $font) {
            if (!$font instanceof SfntFont) {
                throw new \InvalidArgumentException('Collections contain immutable SFNT faces.');
            }
        }
    }

    public function count(): int
    {
        return count($this->fonts);
    }

    public function font(int $index): SfntFont
    {
        return $this->fonts[$index] ?? throw new \OutOfBoundsException('The requested collection face does not exist.');
    }
}
