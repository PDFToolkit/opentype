<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** All declared OS/2 fields. Null means absent in this table version or legacy v0. */
final readonly class Os2Table
{
    public function __construct(
        public int $version,
        public int $xAvgCharWidth,
        public int $usWeightClass,
        public int $usWidthClass,
        public EmbeddingRights $embeddingRights,
        public int $ySubscriptXSize,
        public int $ySubscriptYSize,
        public int $ySubscriptXOffset,
        public int $ySubscriptYOffset,
        public int $ySuperscriptXSize,
        public int $ySuperscriptYSize,
        public int $ySuperscriptXOffset,
        public int $ySuperscriptYOffset,
        public int $yStrikeoutSize,
        public int $yStrikeoutPosition,
        public int $sFamilyClass,
        public Panose $panose,
        public MetricRanges $unicodeRanges,
        public string $achVendId,
        public int $fsSelection,
        public int $usFirstCharIndex,
        public int $usLastCharIndex,
        public ?int $sTypoAscender,
        public ?int $sTypoDescender,
        public ?int $sTypoLineGap,
        public ?int $usWinAscent,
        public ?int $usWinDescent,
        public ?MetricRanges $codePageRanges = null,
        public ?int $sxHeight = null,
        public ?int $sCapHeight = null,
        public ?int $usDefaultChar = null,
        public ?int $usBreakChar = null,
        public ?int $usMaxContext = null,
        public ?int $usLowerOpticalPointSize = null,
        public ?int $usUpperOpticalPointSize = null,
    ) {
        if ($version < 0 || $version > 5 || $embeddingRights->tableVersion !== $version || $usWeightClass < 1 || $usWeightClass > 1000 || $usWidthClass < 1 || $usWidthClass > 9 || strlen($achVendId) !== 4 || count($unicodeRanges->words) !== 4 || ($codePageRanges !== null && count($codePageRanges->words) !== 2)) {
            throw new \InvalidArgumentException('Invalid OS/2 version, classification or field shape.');
        }
        foreach ([$xAvgCharWidth, $ySubscriptXSize, $ySubscriptYSize, $ySubscriptXOffset, $ySubscriptYOffset, $ySuperscriptXSize, $ySuperscriptYSize, $ySuperscriptXOffset, $ySuperscriptYOffset, $yStrikeoutSize, $yStrikeoutPosition, $sFamilyClass, $sTypoAscender, $sTypoDescender, $sTypoLineGap, $sxHeight, $sCapHeight] as $value) {
            if ($value !== null && ($value < -32768 || $value > 32767)) {
                throw new \InvalidArgumentException('An OS/2 signed metric exceeds int16.');
            }
        }
        foreach ([$fsSelection, $usFirstCharIndex, $usLastCharIndex, $usWinAscent, $usWinDescent, $usDefaultChar, $usBreakChar, $usMaxContext, $usLowerOpticalPointSize, $usUpperOpticalPointSize] as $value) {
            if ($value !== null && ($value < 0 || $value > 65535)) {
                throw new \InvalidArgumentException('An OS/2 unsigned metric exceeds uint16.');
            }
        }
        $typo = [$sTypoAscender, $sTypoDescender, $sTypoLineGap, $usWinAscent, $usWinDescent];
        $additional = [$sxHeight, $sCapHeight, $usDefaultChar, $usBreakChar, $usMaxContext];
        if ((in_array(null, $typo, true) && ($version !== 0 || array_filter($typo, fn (?int $v): bool => $v !== null) !== []))
            || (($version >= 1) !== ($codePageRanges !== null))
            || ($version >= 2 ? in_array(null, $additional, true) : array_filter($additional, fn (?int $v): bool => $v !== null) !== [])
            || (($version === 5) !== ($usLowerOpticalPointSize !== null && $usUpperOpticalPointSize !== null))
            || ($version < 5 && ($usLowerOpticalPointSize !== null || $usUpperOpticalPointSize !== null))
        ) {
            throw new \InvalidArgumentException('OS/2 optional fields must match their version.');
        }
        if ($usLowerOpticalPointSize !== null && $usUpperOpticalPointSize !== null) {
            new OpticalPointRange($usLowerOpticalPointSize, $usUpperOpticalPointSize);
        }
    }

    public function useTypographicMetrics(): bool
    {
        return $this->version >= 4 && ($this->fsSelection & 0x80) !== 0;
    }

    public function opticalPointRange(): ?OpticalPointRange
    {
        return $this->usLowerOpticalPointSize === null || $this->usUpperOpticalPointSize === null ? null : new OpticalPointRange($this->usLowerOpticalPointSize, $this->usUpperOpticalPointSize);
    }
}
