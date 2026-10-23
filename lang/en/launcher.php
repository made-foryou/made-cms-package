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
        'password_required' => 'Fill in your password.',
        'password_invalid' => "That password isn't right. Try again, or send yourself a code.",
        'totp_invalid' => "That code isn't right. The code in your app changes every 30 seconds; use the latest one.",
        'recovery_invalid' => "That recovery code isn't right or has already been used.",
        'setup_not_allowed' => 'Log in again with your password to add an authenticator app.',
        'expired' => 'Your session expired. Start again with your e-mail address.',
        'throttled' => 'Easy does it. Try again in :seconds seconds.',
        'code_incomplete' => 'Fill in all digits of the code.',
        'code_invalid' => "That code isn't right or has expired. Try again or ask for a new one.",
    ],
    'bar' => [
        'label' => 'Made CMS edit bar',
        'logout' => 'Log out',
        'home' => 'Home',
        'editor' => 'Logged in as :name',
    ],
    'link' => [
        'title' => 'Log in to :site',
        'confirm' => [
            'heading' => 'Log in to :site',
            'intro' => 'You clicked the link in your e-mail. Click the button to log in; then you go back to the site.',
            'submit' => 'Log in',
        ],
        'other_browser' => [
            'heading' => 'Open the link in the same browser.',
            'intro' => 'This link only works in the browser where you asked for the code. Open the e-mail there, or type the code in the tab where you are logging in.',
        ],
        'expired' => [
            'heading' => 'This link no longer works.',
            'intro' => 'A link works once, for :minutes minutes. Ask for a new code on the site.',
        ],
        'footer' => 'You are logging in to :site, built by MADE.',
    ],
    'no_access' => 'No access yet? Ask the administrator for an invitation.',
];
