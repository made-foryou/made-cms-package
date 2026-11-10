<?php

declare(strict_types=1);

return [
    'kinds' => [
        'image' => 'Afbeelding',
        'document' => 'Document',
        'video' => 'Video',
    ],

    'errors' => [
        'too_large' => 'Te groot: een bestand mag :max zijn.',
        'too_large_video' => 'Te groot: een bestand mag :max zijn. Zet de video op YouTube of Vimeo en plak de link in een videoblok; dat laadt voor bezoekers ook sneller.',
        'type_unknown' => 'Dit type kent de site niet. Sla het op als :types en probeer het opnieuw.',
        'svg_unsafe' => 'Deze svg bevat code die de site niet toelaat, zoals een script of een link naar buiten. Exporteer hem opnieuw als gewone tekening en probeer het nog eens.',
        'unreadable' => 'Dit bestand kunnen we niet lezen. Misschien is het beschadigd; sla het opnieuw op en probeer het nog eens.',
        'alt_long' => 'Een alt-tekst mag hoogstens :max tekens zijn.',
    ],

    'or' => 'of',

    'details' => [
        'dimensions' => ':width × :height',
        'kb' => ':size kB',
        'mb' => ':size MB',
        'decimal' => ',',
    ],
];
