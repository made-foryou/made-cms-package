<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Data\StripImport;
use Made\CmsPackage\Content\Exceptions\InvalidPageImport;
use Made\CmsPackage\Support\PagePath;

/**
 * The initial content of the pages of the site: one JSON file per page in the pages folder of the host,
 * in the order of their file names. Whether the strips fit their types is checked on import.
 */
final class ListPageImports
{
    use AsAction;

    /**
     * @return list<PageImport>
     *
     * @throws InvalidPageImport
     */
    public function handle(): array
    {
        $files = glob(config()->string('made-cms-package.content.pages_path').'/*.json') ?: [];

        sort($files);

        return array_map($this->read(...), $files);
    }

    private function read(string $file): PageImport
    {
        $name = basename($file);

        try {
            $page = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidPageImport::because($name, 'the file is not valid JSON ('.$exception->getMessage().')');
        }

        if (! is_array($page)) {
            throw InvalidPageImport::because($name, 'the file is not an object with a path, a title and strips');
        }

        foreach (array_keys($page) as $key) {
            if (! in_array($key, ['$schema', 'path', 'title', 'strips'], true)) {
                throw InvalidPageImport::because($name, "unknown key [{$key}]");
            }
        }

        $path = $page['path'] ?? null;
        $title = $page['title'] ?? null;
        $strips = $page['strips'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, '/')) {
            throw InvalidPageImport::because($name, 'the path must start with a slash, e.g. "/" or "/lid-worden"');
        }

        if (! is_string($title) || trim($title) === '') {
            throw InvalidPageImport::because($name, 'the page needs a title');
        }

        if (! is_array($strips) || ! array_is_list($strips)) {
            throw InvalidPageImport::because($name, 'the strips must be a list');
        }

        $imports = [];

        foreach ($strips as $index => $strip) {
            $imports[] = $this->strip($name, $index, $strip);
        }

        return new PageImport(path: PagePath::normalise($path), title: trim($title), strips: $imports);
    }

    private function strip(string $name, int $index, mixed $strip): StripImport
    {
        if (! is_array($strip) || ! is_string($strip['type'] ?? null) || array_diff(array_keys($strip), ['type', 'content']) !== []) {
            throw InvalidPageImport::because($name, "strip {$index} needs a type, with only its content next to it");
        }

        $content = $strip['content'] ?? [];

        if (! is_array($content)) {
            throw InvalidPageImport::because($name, "the content of strip {$index} must be an object");
        }

        return new StripImport(type: $strip['type'], content: $content);
    }
}
