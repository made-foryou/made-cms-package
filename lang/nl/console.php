<?php

declare(strict_types=1);

return [
    'editor' => [
        'name' => 'Naam',
        'roles' => 'Rollen (kies er één of meer, gescheiden door komma’s)',
        'invalid_role' => ':role is geen bestaande rol. Kies uit: :roles.',
        'no_role' => 'Een account heeft minstens één rol nodig.',
        'password' => 'Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)',
        'invalid_email' => ':email is geen geldig e-mailadres.',
        'exists' => 'Er bestaat al een account voor :email.',
        'created' => 'Account voor :email aangemaakt.',
    ],
    'pages' => [
        'none' => 'Geen paginabestanden gevonden in :folder.',
        'created' => 'aangemaakt met :count strook|aangemaakt met :count stroken',
        'skipped' => 'bestaat al, overgeslagen',
        'failed' => ':path is niet aangemaakt: :reason',
    ],
];
