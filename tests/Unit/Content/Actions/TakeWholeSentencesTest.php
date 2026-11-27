<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\TakeWholeSentences;

it('takes whole sentences as long as they fit', function (string $text, int $limit, ?string $sentences): void {
    expect(TakeWholeSentences::run($text, $limit))->toBe($sentences);
})->with([
    'up to the end of the sentence that still fits' => [
        'We trainen op dinsdag en donderdag van 19:30 tot 22:00 in Mountain Network, Dordrecht. Kom gerust een keer vrijblijvend meespelen. Neem je eigen batje mee als je er een hebt.',
        155,
        'We trainen op dinsdag en donderdag van 19:30 tot 22:00 in Mountain Network, Dordrecht. Kom gerust een keer vrijblijvend meespelen.',
    ],
    'the paragraphs as one text' => ["Eerste alinea.\nTweede alinea.", 155, 'Eerste alinea. Tweede alinea.'],
    'a line without an end, like a heading, left out' => ["Trainingstijden\nWij trainen op dinsdag. En dan nog wat", 155, 'Wij trainen op dinsdag.'],
    'an abbreviation inside a sentence' => ['Wij spelen o.a. tafeltennis en padel. Kom langs.', 155, 'Wij spelen o.a. tafeltennis en padel. Kom langs.'],
    'a time and a number inside a sentence' => ['De zaal gaat om 19.30 uur open, 2.5 uur lang.', 155, 'De zaal gaat om 19.30 uur open, 2.5 uur lang.'],
    'quotes and an ellipsis' => ['Hij zei: "Kom langs!" Dat doen we… Echt waar.', 155, 'Hij zei: "Kom langs!" Dat doen we… Echt waar.'],
    'a question and an exclamation' => ['Kom je ook? Leuk! Tot dan.', 18, 'Kom je ook? Leuk!'],
    'characters, not bytes' => ['Café 🏓 is open. Ja.', 20, 'Café 🏓 is open. Ja.'],
    'up to the limit itself' => ['Een. Twee.', 10, 'Een. Twee.'],
    'nothing when the first sentence is too long' => [str_repeat('lang ', 40).'zin. Kort.', 155, null],
    'nothing without a sentence' => ['Welkom bij TTV Merwestad', 155, null],
    'nothing in an empty text' => ['', 155, null],
]);
