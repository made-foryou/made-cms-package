<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the scan of a site proposes: the strip types it recognised and, per page, the strips with their values.
 *
 * The review, the generated definitions and views, the import of the content and the AI step all build on this
 * form; docs/scanresultaat.md describes it.
 *
 * @phpstan-import-type ScanTypeArray from ScanType
 * @phpstan-import-type ScanPageArray from ScanPage
 *
 * @phpstan-type ScanResultArray array{version: int, types: list<ScanTypeArray>, pages: list<ScanPageArray>}
 */
final readonly class ScanResult
{
    public const int VERSION = 1;

    /**
     * @param  list<ScanType>  $types  in the order the scan met them
     * @param  list<ScanPage>  $pages
     */
    public function __construct(
        public array $types,
        public array $pages,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $types = [];

        foreach (is_array($data['types'] ?? null) ? $data['types'] : [] as $type) {
            if (is_array($type)) {
                $types[] = ScanType::fromArray($type);
            }
        }

        $pages = [];

        foreach (is_array($data['pages'] ?? null) ? $data['pages'] : [] as $page) {
            if (is_array($page)) {
                $pages[] = ScanPage::fromArray($page);
            }
        }

        return new self($types, $pages);
    }

    public function type(string $key): ?ScanType
    {
        foreach ($this->types as $type) {
            if ($type->key === $key) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return ScanResultArray
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'types' => array_map(static fn (ScanType $type): array => $type->toArray(), $this->types),
            'pages' => array_map(static fn (ScanPage $page): array => $page->toArray(), $this->pages),
        ];
    }
}
