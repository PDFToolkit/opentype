<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** Font-declared OS/2 embedding flags, not an independent grant of licence rights. */
final readonly class EmbeddingRights
{
    public function __construct(public int $fsType, public int $tableVersion)
    {
        if ($fsType < 0 || $fsType > 65535 || $tableVersion < 0 || $tableVersion > 5 || ($fsType & 1) !== 0
            || ($tableVersion >= 3 && !in_array($fsType & 15, [0, 2, 4, 8], true))
        ) {
            throw new \InvalidArgumentException('Invalid embedding flags for this OS/2 version.');
        }
    }

    public function permission(): EmbeddingPermission
    {
        // Versions 0–2 permit the least restrictive indicated permission.
        return match (true) {
            ($this->fsType & 8) !== 0 => EmbeddingPermission::Editable,
            ($this->fsType & 4) !== 0 => EmbeddingPermission::PreviewAndPrint,
            ($this->fsType & 2) !== 0 => EmbeddingPermission::Restricted,
            default => EmbeddingPermission::Installable,
        };
    }

    public function noSubsetting(): bool
    {
        return $this->tableVersion >= 2 && ($this->fsType & 0x0100) !== 0;
    }

    public function bitmapOnly(): bool
    {
        return $this->tableVersion >= 2 && ($this->fsType & 0x0200) !== 0;
    }
}
