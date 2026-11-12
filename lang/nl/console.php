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
    'prune' => [
        'pruned' => '{0} Geen pagina’s definitief verwijderd.|{1} 1 pagina definitief verwijderd.|[2,*] :count pagina’s definitief verwijderd.',
    ],
    'media_usage' => [
        'rebuilt' => 'Gebruik van media opnieuw opgebouwd uit :pages: :places.',
        'pages' => '{0} geen pagina’s|{1} 1 pagina|[2,*] :count pagina’s',
        'places' => '{0} geen bestanden in gebruik|{1} 1 plek met een bestand|[2,*] :count plekken met een bestand',
    ],
    'media_thumbnails' => [
        'made' => ':made; :had.',
        'count' => '{0} Geen miniaturen gemaakt|{1} 1 miniatuur gemaakt|[2,*] :count miniaturen gemaakt',
        'had' => '{0} geen foto had er al een|{1} 1 foto had er al een|[2,*] :count foto’s hadden er al een',
        'missing' => 'Geen miniatuur voor :name (#:id): het bestand staat niet op de media-disk.',
        'unreadable' => 'Geen miniatuur voor :name (#:id): het bestand is geen foto die te lezen is.',
    ],
];
