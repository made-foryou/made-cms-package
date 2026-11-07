<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Strips\StripType;

final class CheckStripValues
{
    use AsAction;

    /**
     * Checks the values of a strip against its strip type, a missing field as its default (as the site renders it),
     * and gives them back in their stored form with every field of the type. Values the type does not know are dropped.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ValidationException keyed by the path of the field
     */
    public function handle(StripType $type, array $values): array
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make([...$type->defaults(), ...$values], $type->rules(), $type->messages(), $type->attributes())->validate();

        return [...$type->defaults(), ...$type->normalize($validated)];
    }
}
