<?php

declare(strict_types=1);

return [
    'login_code' => [
        'subject' => ':code is je inlogcode voor :site',
        'preheader' => 'Werkt :minutes minuten. Vroeg jij hem niet aan? Negeer deze mail.',
        'greeting_named' => 'Hoi :name, hier is je inlogcode.',
        'greeting' => 'Hoi, hier is je inlogcode.',
        'intro' => 'Typ hem over in het tabblad dat openstaat, of klik op de knop — dan logt dat tabblad vanzelf in.',
        'label' => 'Inlogcode',
        'code_label' => 'Code :code',
        'expires' => 'Werkt :minutes minuten, tot :time. Daarna vraag je gewoon een nieuwe aan.',
        'action' => 'Log direct in op :site',
        'fallback' => 'Werkt de knop niet? Plak dit in je browser:',
        'warning_title' => 'Vroeg jij deze code niet aan?',
        'warning' => 'Dan kun je deze mail negeren; zonder de code gebeurt er niets. Deel de code met niemand — ook niet met iemand die zegt van MADE te zijn. Wij vragen er nooit om.',
        'requested' => 'Aangevraagd op :date om :time.',
        'requested_from' => 'Aangevraagd op :date om :time vanaf :device.',
        'device_browser' => ':device (:browser)',
        'footer_reason' => 'Je krijgt deze mail omdat iemand met dit adres wilde inloggen in het beheer van :site. Dit is geen nieuwsbrief en je hoeft je nergens voor af te melden.',
        'footer_built' => 'Gebouwd door',
        'footer_people' => '— de mensen die je site bouwden zijn de mensen die je spreekt.',
        'help' => 'Hulp bij inloggen',
        'text_greeting_named' => 'Hoi :name, hier is je inlogcode voor :site: :code',
        'text_greeting' => 'Hoi, hier is je inlogcode voor :site: :code',
        'text_link' => 'Werkt :minutes minuten, tot :time. Of log direct in: :url',
        'text_warning' => 'Vroeg jij deze code niet aan? Negeer deze mail. Deel de code met niemand.',
    ],
    'devices' => [
        'mac' => 'een Mac',
        'windows' => 'een Windows-pc',
        'iphone' => 'een iPhone',
        'ipad' => 'een iPad',
        'android' => 'een Android-toestel',
        'linux' => 'een Linux-computer',
    ],
];
