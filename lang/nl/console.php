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
];
