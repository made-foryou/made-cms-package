<?php

declare(strict_types=1);

return [
    'strip' => 'strook',
    'keys' => [
        'heading' => 'kop',
        'text' => 'label',
        'paragraph' => 'tekst',
        'rich_text' => 'inhoud',
        'list' => 'lijst',
        'button' => 'knop',
        'link' => 'link',
        'image' => 'afbeelding',
        'icon' => 'icoon',
        'items' => 'items',
    ],
    'doubts' => [
        'generic_name' => 'Zonder id of herkenbare klasse: de naam is algemeen.',
        'contains_form' => 'Bevat een formulier; de velden daarvan zijn niet overgenomen.',
        'decorative_image' => 'Afbeelding zonder alt-tekst of verborgen voor schermlezers: misschien versiering.',
        'background_image' => 'Achtergrondafbeelding uit de stijl van het element.',
        'icon_choice' => 'Keuze geraden uit de iconen van de items.',
        'items_differ' => 'De items zijn niet allemaal precies gelijk opgebouwd.',
        'nested_list' => 'Een lijst in een item: één waarde per regel.',
        'same_in_every_item' => 'Staat in elk item hetzelfde: misschien vaste tekst van het ontwerp.',
    ],
    'generate' => [
        'left_out' => ':field: wacht op het veldtype Afbeelding.',
        'skipped' => [
            'invalid' => 'De definitie klopt niet.',
            'key_exists' => 'Er is al een striptype met deze key.',
            'class_exists' => 'Er is al een klasse met deze naam.',
        ],
        'written' => '{0} Geen striptypes geschreven|{1} 1 striptype geschreven|[2,*] :count striptypes geschreven',
        'skipped_count' => '{1} 1 overgeslagen|[2,*] :count overgeslagen',
        'errors' => [
            'version' => 'Dit scanresultaat komt uit een andere versie van de scan.',
            'types' => 'Het scanresultaat heeft geen striptypes met velden in de goede vorm.',
        ],
    ],
];
