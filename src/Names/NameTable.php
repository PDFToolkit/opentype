<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Names;

final readonly class NameTable
{
    /**
     * @param list<NameRecord> $records
     * @param list<string> $languageTags
     */
    public function __construct(public int $version, public array $records, public array $languageTags = [])
    {
        if (!in_array($version, [0, 1], true) || !array_is_list($records) || !array_is_list($languageTags) || ($version === 0 && $languageTags !== [])) {
            throw new \InvalidArgumentException('Invalid name table version or record ordering.');
        }
        foreach ($records as $record) {
            if (!$record instanceof NameRecord) {
                throw new \InvalidArgumentException('Name tables contain immutable name records.');
            }
        }
        foreach ($languageTags as $tag) {
            if (!is_string($tag)) {
                throw new \InvalidArgumentException('Language tags must be decoded strings.');
            }
        }
    }

    /**
     * Exact selection; no implicit locale, platform preference, fallback or decoding.
     * @return list<NameRecord>
     */
    public function select(int $nameId, int $platformId, int $encodingId, int $languageId): array
    {
        return array_values(array_filter($this->records, fn (NameRecord $record): bool => $record->usableLanguage && $record->nameId === $nameId && $record->platformId === $platformId && $record->encodingId === $encodingId && $record->languageId === $languageId));
    }
}
