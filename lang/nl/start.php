<?php

declare(strict_types=1);

return [
    'greeting' => [
        'morning' => 'Goedemorgen',
        'afternoon' => 'Goedemiddag',
        'evening' => 'Goedenavond',
    ],
    'welcome' => ':greeting, :name.',
    'welcome_anonymous' => ':greeting.',
    'ask' => [
        'label' => 'Aan de slag',
        'question' => 'Waar wil je mee aan de slag?',
        'placeholder' => 'Typ wat je wilt doen, of zoek een pagina — bijvoorbeeld ‘nieuwsbericht’ of ‘trainingstijden’',
    ],
    'recent' => [
        'heading' => 'Verder waar je was',
        'all' => 'Alles wat je bewerkte',
        'status' => [
            'draft' => 'Concept',
            'live' => 'Live',
        ],
    ],
    'attention' => [
        'heading' => 'Hier moet iets mee',
        'count' => '{0} Niets met voorrang|{1} :count punt, :urgent met voorrang|[2,*] :count punten, :urgent met voorrang',
        'severity' => [
            'high' => 'Met voorrang',
            'medium' => 'Binnenkort',
            'low' => 'Als je tijd hebt',
        ],
        'empty' => 'Er is nu niets dat om aandacht vraagt.',
    ],
    'figures' => [
        'heading' => 'Hoe je site het doet',
        'period' => 'Afgelopen 7 dagen',
    ],
    'quick' => [
        'heading' => 'Snel naar',
    ],
    'help' => [
        'label' => 'Hulp',
        'heading' => 'Kom je er niet uit?',
        'intro' => 'De mensen die je site bouwden zijn de mensen die je spreekt. Geen ticketsysteem.',
        'link' => 'Stuur MADE een bericht',
    ],
    'footer' => 'Cijfers komen uit de statistieken van je site, aandachtspunten uit de controle die het CMS elke nacht draait.',
];
