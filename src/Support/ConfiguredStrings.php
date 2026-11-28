<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * A list of texts the developer set in config, read leniently: what is no text or is empty is left out, and a value
 * that is no list reads as none. For a list that only adds to one of the package, so a mistake never breaks a request.
 */
final class ConfiguredStrings
{
    /**
     * @return list<string>
     */
    public static function of(string $key): array
    {
        $configured = config($key);

        if (! is_array($configured)) {
            return [];
        }

        $strings = [];

        foreach ($configured as $value) {
            $value = is_string($value) ? trim($value) : '';

            if ($value !== '') {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
