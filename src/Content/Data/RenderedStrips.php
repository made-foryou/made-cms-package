<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips of a page as a visitor sees them, in the order of the page.
 */
final readonly class RenderedStrips
{
    /**
     * @param  list<RenderedStrip>  $strips
     */
    public function __construct(
        public array $strips,
    ) {}

    /**
     * Every strip as {id, type, ...values}, ready as a prop for Inertia, Vue or React.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (RenderedStrip $strip): array => $strip->toArray(), $this->strips);
    }

    public function isEmpty(): bool
    {
        return $this->strips === [];
    }
}
