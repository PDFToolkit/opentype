<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Names;

/** Exact platform/encoding/language/name identity with its unchanged encoded bytes. */
final readonly class NameRecord
{
    public function __construct(
        public int $platformId,
        public int $encodingId,
        public int $languageId,
        public int $nameId,
        public string $bytes,
        public ?string $languageTag = null,
        public bool $usableLanguage = true,
    ) {
        foreach ([$platformId, $encodingId, $languageId, $nameId] as $value) {
            if ($value < 0 || $value > 65535) {
                throw new \InvalidArgumentException('Name record identifiers must fit uint16.');
            }
        }
    }

    public function text(NameDecoder $decoder): string
    {
        return $decoder->decode($this);
    }
}
