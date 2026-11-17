<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

/**
 * The marking of emphasis in a text of a field with emphasis(): a word or more between asterisks, "Jullie dag in
 * *vertrouwde* handen", also inside a word ("Privacy*verklaring*"). The text inside starts and ends with a character
 * that is no space or asterisk, so an asterisk without a partner, "2 * 3 * 4" or "* a *" stays an asterisk.
 *
 * It is only the rule: the stored and rendered value keep the asterisks, and the view that prints the text makes the
 * parts with emphasis an <em>. The edit shell (edit.js, panel.js) and the examples in the README use the same pattern;
 * (*UCP) lets \s match every space, as it does in JavaScript.
 */
final class Emphasis
{
    public const string PATTERN = '/(*UCP)\*([^\s*](?:[^*]*[^\s*])?)\*/u';

    /**
     * The text in its parts, in order and without empty parts: the parts with emphasis without their asterisks.
     *
     * @return list<array{text: string, emphasis: bool}>
     */
    public static function parts(string $text): array
    {
        $pieces = preg_split(self::PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        // A text that is no valid UTF-8 has no emphasis to find; it stays as it is.
        if ($pieces === false) {
            return $text === '' ? [] : [['text' => $text, 'emphasis' => false]];
        }

        $parts = [];

        foreach ($pieces as $index => $piece) {
            if ($piece !== '') {
                $parts[] = ['text' => $piece, 'emphasis' => $index % 2 === 1];
            }
        }

        return $parts;
    }

    /**
     * The text as a visitor reads it: without the asterisks around its emphasis.
     */
    public static function plain(string $text): string
    {
        return implode('', array_column(self::parts($text), 'text'));
    }
}
