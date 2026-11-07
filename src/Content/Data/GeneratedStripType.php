<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A class the scan wrote into the site for one of its strip types.
 */
final readonly class GeneratedStripType
{
    /**
     * @param  string  $class  the class with its namespace
     * @param  string  $path  the file of the class
     * @param  list<string>  $leftOut  the fields of the scan that wait for a field of the package, by path
     */
    public function __construct(
        public string $key,
        public string $class,
        public string $path,
        public array $leftOut = [],
    ) {}
}
