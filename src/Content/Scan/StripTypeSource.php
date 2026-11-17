<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Support\Locale;

/**
 * The source of the class file for a strip type the scan proposes, as the developer would write it: the key, the
 * label when the class name does not read as it, and the fields with only what the scan knows about them. It builds
 * text and never writes; GenerateStripTypes puts the file in the site. A heading gets its level only when that is
 * not the default level of the field.
 *
 * The output passes Pint (Laravel preset) unchanged: imports in order, four spaces, trailing commas, and a list or a
 * choice on lines of its own once it no longer fits on one line.
 */
final class StripTypeSource
{
    /**
     * The longest line, indentation included, before a choice or a list moves to lines of its own.
     */
    private const int LINE_LENGTH = 120;

    /**
     * Names PHP does not allow for a class, in lowercase: the reserved and soft-reserved words.
     */
    private const array RESERVED = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends',
        'false', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable',
        'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'numeric', 'object', 'or', 'parent', 'print',
        'private', 'protected', 'public', 'readonly', 'require', 'require_once', 'resource', 'return', 'self',
        'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor',
        'yield',
    ];

    /**
     * The class name for a strip type key ("over-ons" becomes "OverOns"), with "Strip" behind a name that PHP reserves
     * or that the class file imports: a class may not share its name with an import.
     */
    public static function className(string $key): string
    {
        $class = Str::studly($key);
        $taken = [...self::RESERVED, 'field', 'striptype'];

        foreach (ScannedStripType::FIELDS as $field) {
            $taken[] = strtolower(class_basename($field));
        }

        return in_array(strtolower($class), $taken, true) ? $class.'Strip' : $class;
    }

    /**
     * The class file of a proposed strip type, in the given namespace under the given class name.
     */
    public static function of(ScannedStripType $type, string $namespace, string $class): string
    {
        $imports = [StripType::class];
        $fields = [];

        foreach ($type->fields() as $field) {
            $fields[] = str_repeat(' ', 12).self::field($field, 12, $imports).",\n";
        }

        $imports = array_unique($imports);
        sort($imports, SORT_STRING | SORT_FLAG_CASE);

        $source = "<?php\n\nnamespace ".trim($namespace, '\\').";\n\n";

        foreach ($imports as $import) {
            $source .= "use {$import};\n";
        }

        $source .= "\nfinal class {$class} extends StripType\n{\n";
        $source .= self::method('key', 'string', 'return '.self::string($type->key()).';');

        if ($type->label() !== Str::headline($class)) {
            $source .= "\n".self::method('label', 'string', 'return '.self::string($type->label()).';');
        }

        $body = '';

        foreach ($type->leftOut() as $path) {
            $body .= '// '.(string) __('made-cms-package::scan.generate.left_out', ['field' => $path], Locale::launcher())."\n        ";
        }

        $body .= $fields === [] ? 'return [];' : "return [\n".implode('', $fields).'        ];';

        return $source."\n".self::method('fields', 'array', $body)."}\n";
    }

    private static function method(string $name, string $returns, string $body): string
    {
        return "    public function {$name}(): {$returns}\n    {\n        {$body}\n    }\n";
    }

    /**
     * One field as a line (or lines) of the list in fields(): its make() and what the scan knows, starting at the
     * given indentation.
     *
     * @param  list<class-string>  $imports
     */
    private static function field(Field $field, int $indent, array &$imports): string
    {
        $imports[] = $field::class;
        $make = class_basename($field).'::make('.self::string($field->getKey());
        $required = $field->isRequired() ? '->required()' : '';

        if ($field instanceof Items) {
            $limits = ($field->getMin() === null ? '' : '->min('.$field->getMin().')')
                .($field->getMax() === null ? '' : '->max('.$field->getMax().')');

            return self::listed($make.', [', self::itemFields($field, $indent, $imports), '])'.$limits.$required, $indent);
        }

        if ($field instanceof Choice) {
            return self::listed($make.', [', self::options($field), '])'.$required, $indent);
        }

        $tag = $field instanceof Heading && $field->getTag() !== Heading::make($field->getKey())->getTag() ? '->tag('.self::string($field->getTag()).')' : '';
        $emphasis = ($field instanceof Text || $field instanceof Heading) && $field->hasEmphasis() ? '->emphasis()' : '';

        return $make.')'.$tag.$emphasis.$required;
    }

    /**
     * @param  list<class-string>  $imports
     * @return list<string>
     */
    private static function itemFields(Items $items, int $indent, array &$imports): array
    {
        $fields = [];

        foreach ($items->getFields() as $field) {
            $fields[] = self::field($field, $indent + 4, $imports);
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private static function options(Choice $choice): array
    {
        $options = $choice->getOptions();

        if (array_is_list($options)) {
            return array_map(self::string(...), $options);
        }

        $entries = [];

        foreach ($options as $value => $label) {
            $entries[] = self::string((string) $value).' => '.self::string($label);
        }

        return $entries;
    }

    /**
     * Entries between an opening and a closing text: on one line when that line fits with its comma and every entry
     * is one line, else an entry per line with a trailing comma.
     *
     * @param  list<string>  $entries
     */
    private static function listed(string $open, array $entries, string $close, int $indent): string
    {
        $inline = $open.implode(', ', $entries).$close;

        if ($indent + strlen($inline) + 1 <= self::LINE_LENGTH && ! str_contains($inline, "\n")) {
            return $inline;
        }

        $lines = '';

        foreach ($entries as $entry) {
            $lines .= str_repeat(' ', $indent + 4).$entry.",\n";
        }

        return $open."\n".$lines.str_repeat(' ', $indent).$close;
    }

    /**
     * A text as a PHP string between single quotes.
     */
    private static function string(string $text): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $text)."'";
    }
}
