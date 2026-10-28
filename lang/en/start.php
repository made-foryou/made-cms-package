<?php

declare(strict_types=1);

return [
    'greeting' => [
        'morning' => 'Good morning',
        'afternoon' => 'Good afternoon',
        'evening' => 'Good evening',
    ],
    'welcome' => ':greeting, :name.',
    'welcome_anonymous' => ':greeting.',
    'ask' => [
        'label' => 'Get started',
        'question' => 'What would you like to work on?',
        'placeholder' => 'Type what you want to do, or search for a page — for example ‘news item’ or ‘opening hours’',
    ],
    'recent' => [
        'heading' => 'Pick up where you left off',
        'all' => 'Everything you edited',
        'status' => [
            'draft' => 'Draft',
            'live' => 'Live',
        ],
    ],
    'attention' => [
        'heading' => 'Needs attention',
        'count' => '{0} Nothing urgent|{1} :count item, :urgent urgent|[2,*] :count items, :urgent urgent',
        'severity' => [
            'high' => 'Urgent',
            'medium' => 'Soon',
            'low' => 'When you have time',
        ],
        'empty' => 'Nothing needs your attention right now.',
    ],
    'figures' => [
        'heading' => 'How your site is doing',
        'period' => 'Last 7 days',
    ],
    'quick' => [
        'heading' => 'Go to',
    ],
    'help' => [
        'label' => 'Help',
        'heading' => 'Stuck?',
        'intro' => 'The people who built your site are the people you talk to. No ticket system.',
        'link' => 'Send MADE a message',
    ],
    'footer' => 'Figures come from your site’s statistics, points of attention from the check the CMS runs every night.',
];
