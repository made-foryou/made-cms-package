<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Support\RelativePath;

/**
 * Writes a strip type to its JSON file in the strips folder of the host. Definitions are code: only the local
 * setup of a site writes them. The file is checked by the rules of reading first, so it always reads back,
 * and written in a fixed order, so a change shows up as a small diff.
 */
final class SaveStripType
{
    use AsAction;

    /**
     * @return string the path of the written file
     *
     * @throws InvalidStripType
     */
    public function handle(StripType $type): string
    {
        $folder = config()->string('made-cms-package.content.strips_path');

        $definition = [
            '$schema' => RelativePath::between($folder, base_path('vendor/made-foryou/made-cms-package/resources/schema/strip.json')),
            'label' => $type->label,
            'fields' => $this->fields($type->fields),
        ];

        // Also refuses a key such as "../evil" before it becomes part of a path.
        ParseStripType::run($type->key, $definition);

        $file = "{$folder}/{$type->key}.json";

        File::ensureDirectoryExists($folder);
        File::put($file, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        return $file;
    }

    /**
     * @param  list<Field>  $fields
     * @return list<array<string, mixed>>
     */
    private function fields(array $fields): array
    {
        return array_map(function (Field $field): array {
            $definition = ['name' => $field->name, 'type' => $field->type->value, 'label' => $field->label];

            if ($field->type === FieldType::List) {
                $definition['fields'] = $this->fields($field->fields);
            }

            return $definition;
        }, $fields);
    }
}
