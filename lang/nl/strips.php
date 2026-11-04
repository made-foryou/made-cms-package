<?php

declare(strict_types=1);

return [
    'button' => [
        'text' => 'Tekst',
        'link' => 'Gaat naar',
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
