<?php

declare(strict_types=1);

return [
    'title' => 'Manage this site',
    'product' => 'Made CMS',
    'open' => 'Manage this site with Made CMS',
    'panel' => 'Log in to Made CMS',
    'close' => 'Close',
    'step' => 'Step :current of :total',
    'email' => [
        'heading' => 'Welcome back.',
        'intro' => 'You are about to manage :site. Which e-mail address do you log in with?',
        'label' => 'E-mail address',
        'placeholder' => 'name@company.com',
        'hint' => 'Enter works too.',
        'submit' => 'Continue',
    ],
    'errors' => [
        'email_required' => 'Fill in your e-mail address.',
        'email_invalid' => "That doesn't look like an e-mail address. Is there an @ in it?",
        'expired' => 'Your session expired. Start again with your e-mail address.',
        'throttled' => 'Easy does it. Try again in :seconds seconds.',
    ],
    'no_access' => 'No access yet? Ask the administrator for an invitation.',
];
