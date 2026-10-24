<?php

declare(strict_types=1);

return [
    'login_code' => [
        'subject' => ':code is your login code for :site',
        'preheader' => "Works for :minutes minutes. Didn't ask for it? Ignore this e-mail.",
        'greeting_named' => 'Hi :name, here is your login code.',
        'greeting' => 'Hi, here is your login code.',
        'intro' => 'Type it in the tab that is open, or click the button — that tab then logs in by itself.',
        'label' => 'Login code',
        'code_label' => 'Code :code',
        'expires' => 'Works for :minutes minutes, until :time. After that, simply ask for a new one.',
        'action' => 'Log in to :site directly',
        'fallback' => 'Button not working? Paste this in your browser:',
        'warning_title' => "Didn't ask for this code?",
        'warning' => 'Then you can ignore this e-mail; without the code nothing happens. Never share the code — not even with someone who says they are from MADE. We never ask for it.',
        'requested' => 'Requested on :date at :time.',
        'requested_from' => 'Requested on :date at :time from :device.',
        'device_browser' => ':device (:browser)',
        'footer_reason' => 'You get this e-mail because someone with this address wanted to log in to the management of :site. This is not a newsletter and there is nothing to unsubscribe from.',
        'footer_built' => 'Built by',
        'footer_people' => '— the people who built your site are the people you talk to.',
        'help' => 'Help with logging in',
        'text_greeting_named' => 'Hi :name, here is your login code for :site: :code',
        'text_greeting' => 'Hi, here is your login code for :site: :code',
        'text_link' => 'Works for :minutes minutes, until :time. Or log in directly: :url',
        'text_warning' => "Didn't ask for this code? Ignore this e-mail. Never share the code.",
    ],
    'devices' => [
        'mac' => 'a Mac',
        'windows' => 'a Windows PC',
        'iphone' => 'an iPhone',
        'ipad' => 'an iPad',
        'android' => 'an Android device',
        'linux' => 'a Linux computer',
    ],
];
