<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the scan read on one page. Combining the pages of a site turns these into a ScanResult; until then they
 * travel or wait in this form, so it reads back what it writes.
 *
 * @phpstan-import-type RecognisedStripArray from RecognisedStrip
 *
 * @phpstan-type RecognisedPageArray array{url: string, title: string, strips: list<RecognisedStripArray>}
 */
final readonly class RecognisedPage
{
    /**
     * @param  list<RecognisedStrip>  $strips  in the order of the page
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
                $strips[] = RecognisedStrip::fromArray($strip);
            }
        }

        return new self(
            url: is_scalar($data['url'] ?? null) ? (string) $data['url'] : '',
            title: is_scalar($data['title'] ?? null) ? (string) $data['title'] : '',
            strips: $strips,
        );
    }

    /**
     * @return RecognisedPageArray
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'strips' => array_map(static fn (RecognisedStrip $strip): array => $strip->toArray(), $this->strips),
        ];
    }
}
