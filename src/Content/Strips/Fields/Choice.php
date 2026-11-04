<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use BackedEnum;
use Illuminate\Validation\Rule;

/**
 * One value out of a fixed set of options.
 *
 * The options are a list ("Primair", "Secundair"), values with labels
 * (["primary" => "Primair"]) or a backed enum; a case gets its label from
 * a label() method when the enum has one, otherwise from its name.
 */
final class Choice extends Field
{
    /**
     * @var array<array-key, string>|string
     */
    private array|string $source = [];

    /**
     * @param  array<array-key, string>|class-string<BackedEnum>  $options
     */
    public static function make(string $key, array|string $options): self
    {
        $field = new self($key);
        $field->source = $options;

        return $field;
    }

    public function type(): string
    {
        return 'choice';
    }

    /**
     * The options as value => label.
     *
     * @return array<array-key, string>
     */
    public function getOptions(): array
    {
        if (is_array($this->source)) {
            return array_is_list($this->source)
                ? array_combine($this->source, $this->source)
                : $this->source;
        }

        if (! is_subclass_of($this->source, BackedEnum::class)) {
            return [];
        }

        $options = [];

        foreach ($this->source::cases() as $case) {
            $options[$case->value] = method_exists($case, 'label') ? (string) $case->label() : $case->name;
        }

        return $options;
    }

    /**
     * The options as a list of {value, label}: an object keyed by digits would lose its order in JavaScript.
     */
    protected function details(): array
    {
        $options = [];

        foreach ($this->getOptions() as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return ['options' => $options];
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', Rule::in(array_map(strval(...), array_keys($this->getOptions())))]];
    }
}
