<?php

declare(strict_types=1);

return [
    'editor' => [
        'name' => 'Name',
        'roles' => 'Roles (pick one or more, separated by commas)',
        'invalid_role' => ':role is not an existing role. Choose from: :roles.',
        'no_role' => 'An account needs at least one role.',
        'password' => 'Password (leave empty to only log in with a login code)',
        'invalid_email' => ':email is not a valid e-mail address.',
        'exists' => 'An account for :email already exists.',
        'created' => 'Account for :email created.',
    ],
    'pages' => [
        'none' => 'No page files found in :folder.',
        'created' => 'created with :count strip|created with :count strips',
        'skipped' => 'already exists, skipped',
        'failed' => ':path was not created: :reason',
    ],
];
