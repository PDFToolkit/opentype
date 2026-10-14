<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Binary;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;

/** A bounded immutable view sharing its backing bytes with child views. */
final readonly class BinaryView
{
    private string $data;
    private int $length;

    public function __construct(string $bytes, private int $baseOffset = 0, ?int $length = null)
    {
        if (PHP_INT_SIZE < 8) {
            throw new UnsupportedFontException('OpenType binary arithmetic requires 64-bit PHP.');
        }
        $this->data = $bytes;
        $size = strlen($bytes);
        if ($baseOffset < 0 || $baseOffset > $size || ($length !== null && ($length < 0 || $length > $size - $baseOffset))) {
            throw new MalformedFontException('A binary view must remain inside its backing bytes.');
        }
        $this->length = $length ?? $size - $baseOffset;
    }

    public function size(): int
    {
        return $this->length;
    }

    public function bytes(int $offset, int $length): string
    {
        $this->checkRange($offset, $length);

        return substr($this->data, $this->baseOffset + $offset, $length);
    }

    public function uint8(int $offset): int
    {
        $this->checkRange($offset, 1);

        return ord($this->data[$this->baseOffset + $offset]);
    }

    public function uint16(int $offset): int
    {
        $this->checkRange($offset, 2);
        $position = $this->baseOffset + $offset;

        return (ord($this->data[$position]) << 8) | ord($this->data[$position + 1]);
    }

    public function int16(int $offset): int
    {
        $value = $this->uint16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    public function uint24(int $offset): int
    {
        $this->checkRange($offset, 3);
        $position = $this->baseOffset + $offset;

        return (ord($this->data[$position]) << 16) | (ord($this->data[$position + 1]) << 8) | ord($this->data[$position + 2]);
    }

    public function uint32(int $offset): int
    {
        $this->checkRange($offset, 4);
        $position = $this->baseOffset + $offset;

        return (ord($this->data[$position]) << 24) | (ord($this->data[$position + 1]) << 16)
            | (ord($this->data[$position + 2]) << 8) | ord($this->data[$position + 3]);
    }

    public function view(int $offset, ?int $length = null): self
    {
        $this->checkRange($offset, 0);
        $length ??= $this->length - $offset;
        $this->checkRange($offset, $length);

        return new self($this->data, $this->baseOffset + $offset, $length);
    }

    private function checkRange(int $offset, int $length): void
    {
        if ($offset < 0 || $length < 0 || $offset > $this->length || $length > $this->length - $offset) {
            throw new MalformedFontException('A font offset or length exceeds its enclosing byte sequence.');
        }
    }
}
