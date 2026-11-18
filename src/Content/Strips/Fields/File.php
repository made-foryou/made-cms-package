<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\MediaChoice;

/**
 * A document or a video of the media library, stored as the id of its file, so a file changes everywhere it is used:
 * a strip such as "Downloads" links to a pdf of the library.
 *
 * It renders as {url, name, extension, kind, mime, size, description}: the url is the lasting link of the file, which
 * keeps leading to it after a new version or a new name, also when a visitor shared it. The description is the alt
 * text of the file, the same on every page; a text of the strip may say it in its own words. A file that is gone, or
 * of a kind the field does not take, renders as null; saving or publishing the strip refuses it. A file has no
 * default, because the id of a file differs per database.
 *
 * @phpstan-import-type RenderedFile from MediaFile
 */
final class File extends Field
{
    /**
     * @var list<MediaKind>
     */
    private array $kinds = [MediaKind::Document, MediaKind::Video];

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * The kinds of file it takes: a document, a video or both. The catalogue refuses an image (that is an Image field)
     * and no kind at all.
     */
    public function kinds(MediaKind ...$kinds): self
    {
        $field = clone $this;
        $field->kinds = array_values(array_filter(MediaKind::cases(), static fn (MediaKind $kind): bool => in_array($kind, $kinds, true)));

        return $field;
    }

    /**
     * @return list<MediaKind>
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    public function type(): string
    {
        return 'file';
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), MediaChoice::rule($this->kinds, 'file', 'file_missing', 'file_kind_'.$this->kindName())]];
    }

    /**
     * The id of the file as a number; any other value (of a former field type, an address of the scan) is no file.
     */
    public function normalize(mixed $value): ?int
    {
        return MediaChoice::id($value);
    }

    public function places(mixed $value, string $path): array
    {
        $id = MediaChoice::id($value);

        return $id === null ? [] : [$path => $id];
    }

    /**
     * @return RenderedFile|null
     */
    public function render(mixed $value, ?MediaFiles $media = null): ?array
    {
        $id = MediaChoice::id($value);

        return $id === null ? null : $media?->file($id, $this->kinds)?->download();
    }

    /**
     * @return array{kinds: list<string>}
     */
    protected function details(): array
    {
        return ['kinds' => array_map(static fn (MediaKind $kind): string => $kind->value, $this->kinds)];
    }

    /**
     * The kinds it takes in one word: the kind itself, or "file" for more than one.
     */
    private function kindName(): string
    {
        return count($this->kinds) === 1 ? $this->kinds[0]->value : 'file';
    }
}
