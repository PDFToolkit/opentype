<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class AttachmentAnchors
{
    /** @param list<list<Anchor|null>> $components */
    public function __construct(public array $components)
    {
        if (!array_is_list($components) || $components === []) {
            throw new \InvalidArgumentException('An attachment target requires at least one component.');
        }
        foreach ($components as $component) {
            if (!array_is_list($component)) {
                throw new \InvalidArgumentException('Attachment anchors must be ordered by class.');
            }
            foreach ($component as $anchor) {
                if ($anchor !== null && !$anchor instanceof Anchor) {
                    throw new \InvalidArgumentException('An attachment class contains an Anchor or null.');
                }
            }
        }
    }
}
