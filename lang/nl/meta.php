<?php

declare(strict_types=1);

return [
    'intro' => [
        'lead' => 'Titel en omschrijving zijn wat Google van een pagina laat zien.',
        'points' => '{1} Elke pagina hoort er één van elk te hebben, kort genoeg om niet afgekapt te worden. Eén pagina haalt dat nu niet; je loopt hem hier af.|[2,*] Elke pagina hoort er één van elk te hebben, kort genoeg om niet afgekapt te worden. :count pagina’s halen dat nu niet; je loopt ze hier af in de volgorde van het menu.',
        'finished' => '{1} De pagina die Google toont heeft er een van elk, kort genoeg om niet afgekapt te worden. Er is niets te doen; verandert er iets, dan zie je het hier weer.|[2,*] Alle :count pagina’s die Google toont hebben er een van elk, kort genoeg om niet afgekapt te worden. Er is niets te doen; verandert er iets, dan zie je het hier weer.',
        'none' => 'Google toont nog geen pagina van de site. Een pagina telt hier mee zodra hij gepubliceerd is en Google hem mag tonen.',
    ],
    'work' => '{1} Werk het punt af|[2,*] Werk de :count punten af',
    'filters_label' => 'Welke pagina’s de lijst toont',
    'filters' => [
        'missing' => ':count zonder omschrijving',
        'long' => ':count te lang',
        'ok' => ':count in orde',
        'all' => '{1} :count pagina|[0,*] Alle :count pagina’s',
    ],
    'finished_line' => 'Niets zonder omschrijving, niets te lang.',
    'list' => [
        'heading' => 'Pagina’s',
        'order' => 'In de volgorde van het menu',
        'columns' => 'Titel · Omschrijving',
        'empty' => 'Geen pagina in dit filter.',
        'legend' => 'De balkjes zijn de lengte ten opzichte van wat Google toont: :title tekens voor een titel, :description voor een omschrijving. Rood is leeg of te lang, amber loopt tegen de grens aan.',
        'absent' => [
            'draft' => 'Concept: Google toont hem nog niet',
            'hidden' => 'Verborgen: Google toont hem niet',
            'noindex' => 'Noindex: Google toont hem niet',
        ],
    ],
    'bar' => [
        'title' => 'Titel',
        'description' => 'Omschrijving',
        'missing' => ':field ontbreekt',
        'long' => ':field :count tekens, te lang',
        'near' => ':field :count van :limit tekens, bijna te lang',
        'fits' => ':field :count van :limit tekens',
    ],
    'panel' => [
        'place' => 'Pagina :position van :total',
        'done' => ':done van :total gedaan',
        'single' => [
            'ok' => 'In orde',
            'draft' => 'Concept: Google toont hem nog niet',
            'hidden' => 'Verborgen: Google toont hem niet',
            'noindex' => 'Noindex: Google toont hem niet',
        ],
        'preview' => 'Zo toont Google het, ongeveer',
        'preview_empty' => 'Geen omschrijving: Google kiest zelf een stuk tekst van de pagina.',
        'title' => 'Titel',
        'description' => 'Omschrijving',
        'description_placeholder' => 'Eén of twee zinnen die zeggen wat iemand op deze pagina vindt.',
        'count' => ':count / :limit',
        'suggestion' => [
            'page_text' => 'Voorstel uit de paginatekst',
            'use' => 'Gebruik dit voorstel',
            'fits' => ':count tekens, past.',
        ],
        'save_next' => 'Opslaan en volgende',
        'save' => 'Opslaan',
        'skip' => 'Overslaan',
        'note' => 'Opslaan zet het direct live; de sitemap gaat vanzelf mee.',
    ],
    'finished' => [
        'badge' => 'Alles in orde',
        'heading' => 'Elke pagina heeft een titel en een omschrijving die passen.',
        'text' => 'Titels blijven onder de :title tekens, omschrijvingen onder de :description. Maak je een nieuwe pagina of verander je een titel, dan zie je hem hier vanzelf weer terug.',
        'hint' => 'Wil je toch iets aanpassen? Klik een pagina in de lijst.',
        'next' => 'Wat je nu kunt doen',
    ],
];
