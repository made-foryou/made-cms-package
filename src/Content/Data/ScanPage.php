<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A scanned page in the result of the scan: its address, its title and its strips in order.
 *
 * @phpstan-import-type ScanStripArray from ScanStrip
 *
 * @phpstan-type ScanPageArray array{url: string, title: string, strips: list<ScanStripArray>}
 */
final readonly class ScanPage
{
    /**
     * @param  list<ScanStrip>  $strips
     */
    public function __construct(
        public string $url,
        public string $title,
        public array $strips,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $strips = [];

        foreach (is_array($data['strips'] ?? null) ? $data['strips'] : [] as $strip) {
            if (is_array($strip)) {
                $strips[] = ScanStrip::fromArray($strip);
            }
        }

        return new self(
            url: is_scalar($data['url'] ?? null) ? (string) $data['url'] : '',
            title: is_scalar($data['title'] ?? null) ? (string) $data['title'] : '',
            strips: $strips,
        );
    }

    /**
     * @return ScanPageArray
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'strips' => array_map(static fn (ScanStrip $strip): array => $strip->toArray(), $this->strips),
        ];
    }
}
