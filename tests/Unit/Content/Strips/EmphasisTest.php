<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Emphasis;

it('splits a text into the parts with and without emphasis', function (string $text, array $parts): void {
    expect(Emphasis::parts($text))->toBe($parts);
})->with([
    'one word' => ['Jullie dag in *vertrouwde* handen', [
        ['text' => 'Jullie dag in ', 'emphasis' => false],
        ['text' => 'vertrouwde', 'emphasis' => true],
        ['text' => ' handen', 'emphasis' => false],
    ]],
    'more than one piece' => ['*Samen* spelen, *samen winnen*', [
        ['text' => 'Samen', 'emphasis' => true],
        ['text' => ' spelen, ', 'emphasis' => false],
        ['text' => 'samen winnen', 'emphasis' => true],
    ]],
    'inside a word' => ['Privacy*verklaring*', [
        ['text' => 'Privacy', 'emphasis' => false],
        ['text' => 'verklaring', 'emphasis' => true],
    ]],
    'two pieces against each other' => ['*a**b*', [
        ['text' => 'a', 'emphasis' => true],
        ['text' => 'b', 'emphasis' => true],
    ]],
    'other letters' => ['Een *café* au lait', [
        ['text' => 'Een ', 'emphasis' => false],
        ['text' => 'café', 'emphasis' => true],
        ['text' => ' au lait', 'emphasis' => false],
    ]],
    'no emphasis' => ['Welkom', [['text' => 'Welkom', 'emphasis' => false]]],
    'nothing' => ['', []],
]);

it('keeps an asterisk without a partner, or with a space at the inside, as an asterisk', function (string $text): void {
    expect(Emphasis::parts($text))->toBe([['text' => $text, 'emphasis' => false]])
        ->and(Emphasis::plain($text))->toBe($text);
})->with([
    'one asterisk' => ['Sterren * en meer'],
    'two asterisks' => ['**'],
    'spaces inside' => ['* vertrouwde *'],
    'a space at the end' => ['*vertrouwde *'],
    'a sum' => ['2 * 3 * 4'],
    'a hard space' => ["*\u{00A0}vertrouwde\u{00A0}*"],
    'unfinished' => ['Jullie dag in *vertrouwde'],
]);

it('gives the text without the asterisks of its emphasis', function (): void {
    expect(Emphasis::plain('Jullie dag in *vertrouwde* handen, 2 * 3'))->toBe('Jullie dag in vertrouwde handen, 2 * 3')
        ->and(Emphasis::plain(''))->toBe('');
});
