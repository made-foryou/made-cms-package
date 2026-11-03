<?php

declare(strict_types=1);

return [
    'title' => ':page · :site',
    'product' => 'Made CMS',
    'live' => 'Live',
    'view_site' => 'View the site',
    'menu' => [
        'label' => 'Main navigation',
        'open' => 'Open menu',
        'close' => 'Close menu',
        'hint' => 'Menu',
        'start' => 'Start',
        'help' => 'Help from MADE',
    ],
    'upcoming' => [
        'intro' => 'This part of the CMS is still being built. Once it is ready, you will find it here.',
        'back' => 'Back to the start page',
    ],
    'edit' => [
        'title' => 'Edit :page',
        'bar' => 'Made CMS edit bar',
        'start' => 'To the start page',
        'preview' => 'Preview',
        'publish' => 'Publish',
        'frame' => 'The page :page as visitors see it',
        'no_address' => 'This page has no address on the site yet, so there is nothing to show.',
        'panel' => 'Side panel',
        'strip' => 'Content of the strip',
        'choose' => 'Click a strip on the site to edit its content.',
        'position' => 'Strip :n of :m',
        'fields' => '{0} No fields|{1} :count field|[2,*] :count fields',
        'unknown' => 'Strip',
        'missing' => 'Made CMS: strip :id (:type) is on the page, but the site shows no element with data-cms-strip=":id". Put that attribute on the root of the view of this strip type, or editors cannot choose the strip.',
    ],
];
