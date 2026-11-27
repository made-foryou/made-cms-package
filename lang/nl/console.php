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
        'versions' => '{0} Geen oudere versies opgeruimd.|{1} 1 oudere versie opgeruimd.|[2,*] :count oudere versies opgeruimd.',
    ],
    'publish' => [
        'published' => '{0} Geen geplande pagina’s gepubliceerd.|{1} 1 geplande pagina gepubliceerd.|[2,*] :count geplande pagina’s gepubliceerd.',
        'refused' => '{0} Geen geplande publicaties tegengehouden.|{1} 1 geplande publicatie tegengehouden door een strook: die pagina vraagt aandacht.|[2,*] :count geplande publicaties tegengehouden door een strook: die pagina’s vragen aandacht.',
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
    'media_variants' => [
        'made' => ':made; :had.',
        'count' => '{0} Geen formaten gemaakt|{1} Formaten gemaakt voor 1 foto|[2,*] Formaten gemaakt voor :count foto’s',
        'had' => '{0} geen foto had ze al|{1} 1 foto had ze al|[2,*] :count foto’s hadden ze al',
        'missing' => 'Geen formaten voor :name (#:id): het bestand staat niet op de media-disk.',
        'unreadable' => 'Geen formaten voor :name (#:id): het bestand is geen foto die te lezen is.',
    ],
    'sitemap' => [
        'built' => ':address: :addresses, :errors.',
        'unchanged' => ':address is niet veranderd: :addresses, :errors.',
        'addresses' => '{0} geen adressen|{1} 1 adres|[2,*] :count adressen',
        'errors' => '{0} geen fouten|{1} 1 fout|[2,*] :count fouten',
        'no_routes' => 'Geen sitemap gemaakt: de routes van de site hebben nog geen Route::madeCms().',
    ],
];
