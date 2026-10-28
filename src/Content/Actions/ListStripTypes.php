<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * The strip types of the site: one JSON file per type in the strips folder of the host, the file name is the key.
 * A site without the folder has no strip types yet.
 */
final class ListStripTypes
{
    use AsAction;

    /**
     * @return array<string, StripType> keyed and sorted by key
     *
     * @throws InvalidStripType
     */
    public function handle(): array
    {
        $types = [];

        foreach (glob(config()->string('made-cms-package.content.strips_path').'/*.json') ?: [] as $file) {
            $key = basename($file, '.json');

            try {
                $definition = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw InvalidStripType::because($key, 'the file is not valid JSON ('.$exception->getMessage().')');
            }

            $types[$key] = ParseStripType::run($key, $definition);
        }

        ksort($types);

        return $types;
    }
}
