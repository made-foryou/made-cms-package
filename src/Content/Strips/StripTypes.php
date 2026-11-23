<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

use Illuminate\Container\Container;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Exceptions\DuplicateStripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\File;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Support\ClassesInFolder;

/**
 * The catalogue of strip types the site defines; editors pick from it to build a page.
 *
 * Every definition is checked when the catalogue is built, so a mistake in a strip type
 * shows up at once instead of when an editor first uses it.
 */
final class StripTypes
{
    /**
     * Field names the rendered data of a strip already uses.
     */
    public const array RESERVED_FIELDS = ['id', 'type', 'visible'];

    /**
     * Field names the stored and rendered items of a list already use.
     */
    public const array RESERVED_ITEM_FIELDS = ['id'];

    private const string KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

    private const string FIELD_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @var array<string, StripType>
     */
    private array $types = [];

    /**
     * @param  list<StripType>  $types
     */
    public function __construct(array $types)
    {
        foreach ($types as $type) {
            self::check($type);

            $key = $type->key();

            if (isset($this->types[$key])) {
                throw DuplicateStripType::key($key, $this->types[$key]::class, $type::class);
            }

            $this->types[$key] = $type;
        }

        uasort($this->types, static fn (StripType $a, StripType $b): int => strnatcasecmp($a->label(), $b->label()) ?: strcmp($a->key(), $b->key()));
    }

    /**
     * Builds the catalogue from the strip type classes in a folder and its sub-folders.
     *
     * Class names follow the folder structure under the namespace (PSR-4). Abstract classes
     * and classes that are no strip type are skipped; a missing folder gives an empty catalogue.
     */
    public static function discover(string $path, string $namespace): self
    {
        $container = Container::getInstance();

        return new self(array_map(
            static fn (string $class): StripType => $container->make($class),
            ClassesInFolder::find($path, $namespace, StripType::class),
        ));
    }

    /**
     * Every strip type, sorted by label.
     *
     * @return list<StripType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @throws UnknownStripType
     */
    public function find(string $key): StripType
    {
        return $this->types[$key] ?? throw UnknownStripType::key($key);
    }

    /**
     * @throws InvalidStripType
     */
    private static function check(StripType $type): void
    {
        if (preg_match(self::KEY_PATTERN, $type->key()) !== 1) {
            throw InvalidStripType::key($type::class, $type->key());
        }

        self::checkFields($type::class, $type->fields(), inItems: false);
    }

    /**
     * @param  list<Field>  $fields
     *
     * @throws InvalidStripType
     */
    private static function checkFields(string $type, array $fields, bool $inItems): void
    {
        $seen = [];

        foreach ($fields as $field) {
            $key = $field->getKey();

            if (preg_match(self::FIELD_KEY_PATTERN, $key) !== 1) {
                throw InvalidStripType::fieldKey($type, $key);
            }

            if (in_array($key, $inItems ? self::RESERVED_ITEM_FIELDS : self::RESERVED_FIELDS, true)) {
                throw InvalidStripType::reservedField($type, $key);
            }

            if (isset($seen[$key])) {
                throw InvalidStripType::duplicateField($type, $key);
            }

            $seen[$key] = true;

            if ($field instanceof Choice && $field->getOptions() === []) {
                throw InvalidStripType::emptyChoice($type, $key);
            }

            if ($field instanceof Heading) {
                self::checkHeading($type, $field);
            }

            if ($field instanceof Image && $field->getDefault() !== null) {
                throw InvalidStripType::imageDefault($type, $key);
            }

            if ($field instanceof File) {
                self::checkFile($type, $field);
            }

            if ($field instanceof Items) {
                self::checkItems($type, $field, $inItems);

                if ($field->media($field->getDefault()) !== []) {
                    throw InvalidStripType::defaultWithMedia($type, $key);
                }
            }
        }
    }

    /**
     * @throws InvalidStripType
     */
    private static function checkFile(string $type, File $file): void
    {
        $key = $file->getKey();

        if ($file->getDefault() !== null) {
            throw InvalidStripType::fileDefault($type, $key);
        }

        if ($file->getKinds() === [] || in_array(MediaKind::Image, $file->getKinds(), true)) {
            throw InvalidStripType::fileKinds($type, $key);
        }
    }

    /**
     * @throws InvalidStripType
     */
    private static function checkHeading(string $type, Heading $heading): void
    {
        $key = $heading->getKey();

        foreach ([...$heading->getTags(), $heading->getTag()] as $tag) {
            if (! in_array($tag, Heading::TAGS, true)) {
                throw InvalidStripType::headingTag($type, $key, $tag);
            }
        }

        if (! in_array($heading->getTag(), $heading->getTags(), true)) {
            throw InvalidStripType::defaultHeadingTag($type, $key, $heading->getTag());
        }
    }

    /**
     * @throws InvalidStripType
     */
    private static function checkItems(string $type, Items $items, bool $inItems): void
    {
        $key = $items->getKey();

        if ($inItems) {
            throw InvalidStripType::nestedItems($type, $key);
        }

        $min = $items->getMin();
        $max = $items->getMax();

        if (($min !== null && $min < 0) || ($max !== null && $max < 1) || ($min !== null && $max !== null && $min > $max)) {
            throw InvalidStripType::range($type, $key);
        }

        if ($items->getFields() === []) {
            throw InvalidStripType::emptyItems($type, $key);
        }

        self::checkFields($type, $items->getFields(), inItems: true);
    }
}
