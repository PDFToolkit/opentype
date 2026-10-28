<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

/** Caller supplies one identified typographic family; no system font search occurs. */
final readonly class OpticalSizeFamily
{
    /** @param non-empty-list<OpticalSizeParameters> $members */
    public function __construct(public string $familyName, private array $members)
    {
        if ($familyName === '' || !array_is_list($members) || $members === []) {
            throw new \InvalidArgumentException('An optical family requires a name and explicit member list.');
        }
        $ordered = $members;
        foreach ($members as $member) {
            if (!$member instanceof OpticalSizeParameters || $member->subfamilyId === 0 || $member->subfamilyId !== $members[0]->subfamilyId) {
                throw new \InvalidArgumentException('Optical family members must share a declared nonzero subfamily.');
            }
        }
        usort($ordered, static fn (OpticalSizeParameters $a, OpticalSizeParameters $b): int => $a->rangeStart <=> $b->rangeStart);
        $end = 0;
        foreach ($ordered as $member) {
            if ($member->rangeStart < $end) {
                throw new \InvalidArgumentException('Optical family ranges must not overlap.');
            }
            $end = $member->rangeEnd;
        }
    }

    public function select(float $pointSize): OpticalSizeParameters
    {
        if (!is_finite($pointSize) || $pointSize <= 0) {
            throw new \InvalidArgumentException('An optical point size must be finite and positive.');
        }
        $selected = $this->members[0];
        $distance = INF;
        foreach ($this->members as $member) {
            if ($member->contains($pointSize)) {
                return $member;
            }
            $candidate = max($member->rangeStart - $pointSize * 10, $pointSize * 10 - $member->rangeEnd, 0.0);
            if ($candidate < $distance || ($candidate === $distance && $member->rangeStart > $selected->rangeStart)) {
                $selected = $member;
                $distance = $candidate;
            }
        }

        return $selected;
    }
}
