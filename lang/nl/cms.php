<?php

declare(strict_types=1);

return [
    'title' => ':page · :site',
    'product' => 'Made CMS',
    'live' => 'Live',
    'view_site' => 'Bekijk de site',
    'menu' => [
        'label' => 'Hoofdnavigatie',
        'open' => 'Menu openen',
        'close' => 'Menu sluiten',
        'hint' => 'Menu',
        'start' => 'Start',
        'help' => 'Hulp van MADE',
    ],
    'upcoming' => [
        'intro' => 'Dit onderdeel van het CMS wordt nog gebouwd. Zodra het klaar is, vind je het hier.',
        'back' => 'Terug naar de startpagina',
    ],
    'edit' => [
        'title' => ':page bewerken',
        'bar' => 'Made CMS bewerkbalk',
        'start' => 'Naar de startpagina',
        'preview' => 'Voorvertoning',
        'publish' => 'Publiceren',
        'frame' => 'De pagina :page zoals bezoekers hem zien',
        'no_address' => 'Deze pagina heeft nog geen adres op de site, dus er is nog niets te tonen.',
        'panel' => 'Zijpaneel',
        'strip' => 'Inhoud van de strook',
        'choose' => 'Klik een strook op de site om de inhoud te bewerken.',
        'position' => 'Strook :n van :m',
        'fields' => '{0} Geen velden|{1} :count veld|[2,*] :count velden',
        'unknown' => 'Strook',
        'missing' => 'Made CMS: strook :id (:type) staat op de pagina, maar de site toont geen element met data-cms-strip=":id". Zet dat attribuut op de root van de view van dit striptype, anders kan een redacteur de strook niet kiezen.',
        'errors' => [
            'values' => 'Het paneel stuurde de inhoud van de strook niet mee. Herlaad de pagina en probeer het opnieuw.',
            'visible' => 'Het paneel stuurde niet mee of de strook zichtbaar moet zijn. Herlaad de pagina en probeer het opnieuw.',
        ],
    ],
];
