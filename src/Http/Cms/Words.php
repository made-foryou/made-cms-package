<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

/**
 * Words a screen of the CMS strings together.
 */
final class Words
{
    /**
     * Words as one phrase: "a", "a en b", "a, b en c", with the word that joins the last two ("en", "of").
     *
     * @param  list<string>  $words
     */
    public static function list(array $words, string $joiner): string
    {
        $last = array_pop($words);

        return $words === [] ? (string) $last : implode(', ', $words).' '.$joiner.' '.$last;
    }
}
