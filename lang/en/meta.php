<?php

declare(strict_types=1);

return [
    'intro' => [
        'lead' => 'The title and the description are what Google shows of a page.',
        'points' => '{1} Every page should have one of each, short enough not to be cut off. One page does not yet; you work through it here.|[2,*] Every page should have one of each, short enough not to be cut off. :count pages do not yet; you work through them here in the order of the menu.',
        'finished' => '{1} The page Google shows has one of each, short enough not to be cut off. There is nothing to do; when something changes, you will see it here again.|[2,*] All :count pages Google shows have one of each, short enough not to be cut off. There is nothing to do; when something changes, you will see it here again.',
        'none' => 'Google shows no page of the site yet. A page counts here once it is published and Google may show it.',
    ],
    'work' => '{1} Work through the point|[2,*] Work through the :count points',
    'filters_label' => 'Which pages the list shows',
    'filters' => [
        'missing' => ':count without a description',
        'long' => ':count too long',
        'ok' => ':count in order',
        'all' => '{1} :count page|[0,*] All :count pages',
    ],
    'finished_line' => 'Nothing without a description, nothing too long.',
    'list' => [
        'heading' => 'Pages',
        'order' => 'In the order of the menu',
        'columns' => 'Title · Description',
        'empty' => 'No page in this filter.',
        'legend' => 'The bars are the length against what Google shows: :title characters for a title, :description for a description. Red is empty or too long, amber comes close to the limit.',
        'absent' => [
            'draft' => 'Draft: Google does not show it yet',
            'hidden' => 'Hidden: Google does not show it',
            'noindex' => 'Noindex: Google does not show it',
        ],
    ],
    'bar' => [
        'title' => 'Title',
        'description' => 'Description',
        'missing' => ':field is missing',
        'long' => ':field :count characters, too long',
        'near' => ':field :count of :limit characters, nearly too long',
        'fits' => ':field :count of :limit characters',
    ],
    'panel' => [
        'place' => 'Page :position of :total',
        'done' => ':done of :total done',
        'single' => [
            'ok' => 'In order',
            'draft' => 'Draft: Google does not show it yet',
            'hidden' => 'Hidden: Google does not show it',
            'noindex' => 'Noindex: Google does not show it',
        ],
        'preview' => 'How Google shows it, roughly',
        'preview_empty' => 'No description: Google picks a piece of text of the page itself.',
        'title' => 'Title',
        'description' => 'Description',
        'description_placeholder' => 'One or two sentences that say what someone finds on this page.',
        'count' => ':count / :limit',
        'suggestion' => [
            'page_text' => 'Suggestion from the text of the page',
            'use' => 'Use this suggestion',
            'fits' => ':count characters, fits.',
        ],
        'save_next' => 'Save and next',
        'save' => 'Save',
        'skip' => 'Skip',
        'saved' => 'The title and description of :title are live.',
        'note' => 'Saving puts it live at once; the sitemap follows by itself.',
    ],
    'finished' => [
        'badge' => 'All in order',
        'heading' => 'Every page has a title and a description that fit.',
        'text' => 'Titles stay under :title characters, descriptions under :description. Make a new page or change a title, and you will see it here again by itself.',
        'hint' => 'Want to change something anyway? Click a page in the list.',
        'next' => 'What you can do now',
    ],
];
