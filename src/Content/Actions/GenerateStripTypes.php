<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\GeneratedStripType;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\SkippedStripType;
use Made\CmsPackage\Content\Enums\StripTypeSkip;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Exceptions\NotLocalEnvironment;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Scan\StripTypeSource;
use Made\CmsPackage\Content\Strips\StripTypes;

final class GenerateStripTypes
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Writes a class for every strip type of a (reviewed) scan into the folder where the catalogue finds the strip
     * types of the site (strips.path and strips.namespace). The class is the developer's from then on: this never
     * overwrites. A type is skipped, in this order, when the catalogue would refuse its definition, when the catalogue
     * or the scan already has its key, or when the folder already has its class, whatever the capitals.
     *
     * @throws NotLocalEnvironment outside APP_ENV=local, before anything is written
     */
    public function handle(ScanResult $scan): GeneratedStripTypes
    {
        if (! app()->isLocal()) {
            throw NotLocalEnvironment::forWriting();
        }

        $folder = rtrim(config()->string('made-cms-package.strips.path'), '/\\');
        $namespace = trim(config()->string('made-cms-package.strips.namespace'), '\\');
        $classes = $this->classesIn($folder);
        $keys = [];
        $written = [];
        $skipped = [];

        foreach ($scan->types as $type) {
            $class = StripTypeSource::className($type->key);
            $qualified = $namespace.'\\'.$class;

            try {
                $proposal = ScannedStripType::from($type);
                new StripTypes([$proposal]);
            } catch (InvalidStripType $exception) {
                $skipped[] = new SkippedStripType($type->key, $qualified, StripTypeSkip::Invalid, str_replace(ScannedStripType::class, $qualified, $exception->getMessage()));

                continue;
            }

            if ($this->types->has($type->key) || in_array($type->key, $keys, true)) {
                $skipped[] = new SkippedStripType($type->key, $qualified, StripTypeSkip::KeyExists);

                continue;
            }

            $keys[] = $type->key;
            $path = $folder.'/'.$class.'.php';

            if (in_array(strtolower($class), $classes, true) || class_exists($qualified) || ! $this->create($path, StripTypeSource::of($proposal, $namespace, $class))) {
                $skipped[] = new SkippedStripType($type->key, $qualified, StripTypeSkip::ClassExists);

                continue;
            }

            $classes[] = strtolower($class);
            $written[] = new GeneratedStripType($type->key, $qualified, $path, $proposal->leftOut());
        }

        return new GeneratedStripTypes($written, $skipped);
    }

    /**
     * The names of the class files in the folder, in lowercase: PHP does not tell OverOns from Overons.
     *
     * @return list<string>
     */
    private function classesIn(string $folder): array
    {
        $classes = [];

        foreach (is_dir($folder) ? (scandir($folder) ?: []) : [] as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
                $classes[] = strtolower(pathinfo($file, PATHINFO_FILENAME));
            }
        }

        return $classes;
    }

    /**
     * Creates the file only when it is not there yet (mode x), so not even a request in between gets overwritten.
     */
    private function create(string $path, string $source): bool
    {
        $folder = dirname($path);

        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $file = @fopen($path, 'xb');

        if ($file === false) {
            return false;
        }

        try {
            fwrite($file, $source);
        } finally {
            fclose($file);
        }

        return true;
    }
}
