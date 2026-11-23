<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Finds the classes a site defines in a folder, such as its strip types and its page types.
 *
 * Class names follow the folder structure under the namespace (PSR-4). Abstract classes and
 * classes that do not extend the parent are skipped; a missing folder holds no classes.
 */
final class ClassesInFolder
{
    /**
     * @template TParent of object
     *
     * @param  class-string<TParent>  $parent
     * @return list<class-string<TParent>> in the order of their files
     */
    public static function find(string $path, string $namespace, string $parent): array
    {
        $root = realpath($path);

        if ($root === false || ! is_dir($root)) {
            return [];
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $namespace = trim($namespace, '\\');
        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        $classes = [];

        foreach ($files as $file) {
            $class = $namespace.'\\'.str_replace('/', '\\', substr($file, strlen($root) + 1, -4));

            if (! class_exists($class) || ! is_subclass_of($class, $parent) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
