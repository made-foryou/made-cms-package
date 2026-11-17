<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Validation\Rule;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Enums\ScanRole;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The (reviewed) result of a scan whose strip types become classes in the site.
 *
 * Only what writing the classes needs is checked here: the version, and per type and field the key, label, type,
 * whether it is required, options, the level of a heading and limits. Whether a definition is valid is for the catalogue, through the
 * Action. Selectors, fingerprints, doubts and pages pass unchecked.
 */
final class GenerateStripTypesRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', Rule::in([ScanResult::VERSION])],
            'types' => ['required', 'array', 'list'],
            'types.*' => ['array'],
            'types.*.key' => ['required', 'string', 'distinct'],
            'types.*.label' => ['required', 'string', 'max:255'],
            'types.*.fields' => ['present', 'array', 'list'],
            ...$this->fieldRules('types.*.fields.*'),
            ...$this->fieldRules('types.*.fields.*.fields.*'),
        ];
    }

    /**
     * One message for every rule of the version and one for every rule of the types and their fields, at any depth:
     * a * in a message key stops at a dot.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (array_keys($this->rules()) as $attribute) {
            $messages[$attribute.'.*'] = $this->error($attribute === 'version' ? 'version' : 'types');
        }

        return $messages;
    }

    public function toData(): ScanResult
    {
        return ScanResult::fromArray($this->all());
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::scan.generate.errors.'.$key);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function fieldRules(string $path): array
    {
        $types = array_values(array_unique(array_map(static fn (ScanRole $role): string => $role->type(), ScanRole::cases())));

        return [
            $path => ['array'],
            "{$path}.key" => ['required', 'string'],
            "{$path}.type" => ['required', 'string', Rule::in($types)],
            "{$path}.required" => ['sometimes', 'boolean'],
            "{$path}.options" => ['sometimes', 'array', 'list'],
            "{$path}.options.*" => ['string'],
            "{$path}.tag" => ['sometimes', 'nullable', 'string'],
            "{$path}.emphasis" => ['sometimes', 'boolean'],
            "{$path}.min" => ['sometimes', 'nullable', 'integer'],
            "{$path}.max" => ['sometimes', 'nullable', 'integer'],
            "{$path}.fields" => ['sometimes', 'array', 'list'],
        ];
    }
}
