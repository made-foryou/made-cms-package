<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Enums\MetaField;

/**
 * How a title and a description in Google are checked when an editor saves them, on the card "Hoe Google hem ziet" and
 * in the Meta-overzicht alike: a text longer than Google shows is fine (the counter says so); only one far longer is
 * refused.
 */
final class MetaTexts
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:'.MetaField::Title->maxLength()],
            'meta_description' => ['nullable', 'string', 'max:'.MetaField::Description->maxLength()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'meta_title.*' => (string) __('made-cms-package::pages.errors.meta_title_long'),
            'meta_description.*' => (string) __('made-cms-package::pages.errors.meta_description_long'),
        ];
    }
}
