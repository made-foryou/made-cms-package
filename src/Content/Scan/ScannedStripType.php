<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip type the scan proposes, built from a ScanType with the field API of the package, so the catalogue checks it
 * before its class is written and the class says exactly what this definition says.
 *
 * A heading proposes the level the scan found as its default level, with every level to choose from. A list proposes
 * the most items seen as its max; a min of 1 is left to required, which already asks for one item and
 * leaves an optional list free to be empty. Images wait for the image field (#27): they are left out, and so is a list
 * that holds nothing else.
 */
final class ScannedStripType extends StripType
{
    /**
     * The field class for every field type of the scan that the package has a field for.
     *
     * @var array<string, class-string<Field>>
     */
    public const array FIELDS = [
        'text' => Text::class,
        'heading' => Heading::class,
        'long_text' => LongText::class,
        'rich_text' => RichText::class,
        'button' => Button::class,
        'link' => Link::class,
        'choice' => Choice::class,
        'items' => Items::class,
    ];

    /**
     * Field types of the scan that wait for a field of the package.
     */
    private const array LEFT_OUT = ['image'];

    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $leftOut
     */
    private function __construct(
        private readonly string $stripKey,
        private readonly string $stripLabel,
        private readonly array $fields,
        private readonly array $leftOut,
    ) {}

    /**
     * @throws InvalidStripType for a field type the package has no field for
     */
    public static function from(ScanType $type): self
    {
        $leftOut = [];

        return new self($type->key, $type->label, self::fieldsOf($type->fields, '', $leftOut), $leftOut);
    }

    public function key(): string
    {
        return $this->stripKey;
    }

    public function label(): string
    {
        return $this->stripLabel;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * The fields of the scan that are left out until the package has a field for them, by path ("items.afbeelding").
     *
     * @return list<string>
     */
    public function leftOut(): array
    {
        return $this->leftOut;
    }

    /**
     * @param  list<ScanField>  $scanned
     * @param  list<string>  $leftOut
     * @return list<Field>
     */
    private static function fieldsOf(array $scanned, string $path, array &$leftOut): array
    {
        $fields = [];

        foreach ($scanned as $field) {
            if (in_array($field->type, self::LEFT_OUT, true)) {
                $leftOut[] = $path.$field->key;

                continue;
            }

            $definition = match ($field->type) {
                'text' => Text::make($field->key),
                'heading' => $field->tag === null ? Heading::make($field->key) : Heading::make($field->key)->tag($field->tag),
                'long_text' => LongText::make($field->key),
                'rich_text' => RichText::make($field->key),
                'button' => Button::make($field->key),
                'link' => Link::make($field->key),
                'choice' => Choice::make($field->key, self::options($field->options)),
                'items' => self::items($field, $path, $leftOut),
                default => throw InvalidStripType::fieldType(self::class, $path.$field->key, $field->type),
            };

            if (($definition instanceof Text || $definition instanceof Heading) && $field->emphasis) {
                $definition = $definition->emphasis();
            }

            if ($definition instanceof Field) {
                $fields[] = $field->required ? $definition->required() : $definition;
            }
        }

        return $fields;
    }

    /**
     * A list with its fields, or null when it held only fields that are left out.
     *
     * @param  list<string>  $leftOut
     */
    private static function items(ScanField $field, string $path, array &$leftOut): ?Items
    {
        $fields = self::fieldsOf($field->fields, $path.$field->key.'.', $leftOut);

        if ($fields === [] && $field->fields !== []) {
            return null;
        }

        $items = Items::make($field->key, $fields);

        if ($field->min !== null && $field->min > 1) {
            $items = $items->min($field->min);
        }

        return $field->max === null ? $items : $items->max($field->max);
    }

    /**
     * The options as value => label, so the developer finds a label to translate. The values 0, 1, 2 … stay a list:
     * as keys PHP turns them into a list, which Choice reads as a list of labels.
     *
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    private static function options(array $values): array
    {
        $values = array_values(array_unique($values));

        if ($values === array_map(strval(...), array_keys($values))) {
            return $values;
        }

        $options = [];

        foreach ($values as $value) {
            $options[$value] = Names::label($value);
        }

        return $options;
    }
}
