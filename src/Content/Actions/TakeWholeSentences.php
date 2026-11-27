<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

final class TakeWholeSentences
{
    use AsAction;

    /**
     * A sentence: up to a full stop, an exclamation mark, a question mark or an ellipsis (with the quotes or the bracket
     * that close it), where the next sentence starts with a capital, a number or an opening quote, or the line ends.
     * So "o.a. tafeltennis" and "19.30 uur" stay whole; an abbreviation before a capital ends a sentence.
     */
    private const string SENTENCE = '/\s*(.+?[.!?…]+["”’\')»]*)(?=\s+[\p{Lu}\p{N}"“‘\'(«]|\s*$)/u';

    /**
     * The first whole sentences of a text that fit within a limit together, joined by a space; null when not even the
     * first fits or the text holds no sentence. Every line of the text is a paragraph; what ends a line without ending
     * a sentence (a heading, an item of a list) is no sentence and is left out. Counted in characters, as Google does.
     */
    public function handle(string $text, int $limit): ?string
    {
        $taken = '';

        foreach ($this->sentences($text) as $sentence) {
            $next = $taken === '' ? $sentence : $taken.' '.$sentence;

            if (mb_strlen($next) > $limit) {
                break;
            }

            $taken = $next;
        }

        return $taken === '' ? null : $taken;
    }

    /**
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $sentences = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            preg_match_all(self::SENTENCE, $paragraph, $matches);
            $sentences = [...$sentences, ...array_map(trim(...), $matches[1])];
        }

        return $sentences;
    }
}
