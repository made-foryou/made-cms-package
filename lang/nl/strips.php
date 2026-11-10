<?php

declare(strict_types=1);

return [
    'button' => [
        'text' => 'Tekst',
        'link' => 'Gaat naar',
    ],
    'heading' => [
        'text' => 'Tekst',
        'tag' => 'Niveau',
        'tags' => [
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
            'span' => 'Geen kop',
        ],
        'descriptions' => [
            'h1' => 'De hoofdkop van de pagina, één per pagina.',
            'h2' => 'Een hoofdstuk: de kop van een strook.',
            'h3' => 'Een onderdeel binnen een strook, zoals een kaart.',
            'h4' => 'Een kopje daar weer onder.',
            'h5' => 'Zelden nodig; alleen onder een H4.',
            'h6' => 'Het laagste niveau; bijna nooit nodig.',
            'span' => 'Ziet er hetzelfde uit, maar Google leest het niet als kop.',
        ],
    ],
    'validation' => [
        'required' => 'Vul :attribute in.',
        'string' => ':attribute moet tekst zijn.',
        'array' => ':attribute heeft niet de juiste vorm.',
        'list' => ':attribute moet een lijst zijn.',
        'in' => 'Kies bij :attribute een van de mogelijkheden.',
        'uuid' => ':attribute heeft geen geldige id.',
        'max_string' => ':attribute mag niet langer zijn dan :max tekens.',
        'max_array' => ':attribute mag er niet meer dan :max hebben.',
        'min_string' => ':attribute moet minstens :min tekens lang zijn.',
        'min_array' => ':attribute heeft er minstens :min nodig.',
        'link' => 'Kies bij :attribute een pagina of vul een adres in.',
        'link_page' => 'De gekozen pagina bij :attribute bestaat niet.',
        'link_url' => 'Het adres bij :attribute is ongeldig. Begin met https://, mailto:, tel:, / of #.',
    ],
];
