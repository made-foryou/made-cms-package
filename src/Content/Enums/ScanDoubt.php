<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why the scan is not sure of a strip type or a field; the review of the scan shows these first.
 */
enum ScanDoubt: string
{
    case GenericName = 'generic_name';
    case ContainsForm = 'contains_form';
    case DecorativeImage = 'decorative_image';
    case BackgroundImage = 'background_image';
    case IconChoice = 'icon_choice';
    case ItemsDiffer = 'items_differ';
    case NestedList = 'nested_list';
    case SameInEveryItem = 'same_in_every_item';
    case HeadingLevelsDiffer = 'heading_levels_differ';

    public function label(): string
    {
        return (string) __('made-cms-package::scan.doubts.'.$this->value);
    }

    /**
     * The doubts in the order of the cases, each once.
     *
     * @param  list<self>  $doubts
     * @return list<self>
     */
    public static function sorted(array $doubts): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => in_array($case, $doubts, true)));
    }

    /**
     * @param  mixed  $values  doubts as they are stored; unknown values are left out
     * @return list<self>
     */
    public static function fromValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $doubts = [];

        foreach ($values as $value) {
            $doubt = is_string($value) ? self::tryFrom($value) : null;

            if ($doubt instanceof self) {
                $doubts[] = $doubt;
            }
        }

        return self::sorted($doubts);
    }

    /**
     * @param  list<self>  $doubts
     * @return list<string>
     */
    public static function values(array $doubts): array
    {
        return array_map(static fn (self $doubt): string => $doubt->value, $doubts);
    }
}
