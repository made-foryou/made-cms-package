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
    'prune' => [
        'pruned' => '{0} No pages removed for good.|{1} 1 page removed for good.|[2,*] :count pages removed for good.',
    ],
    'media_usage' => [
        'rebuilt' => 'Rebuilt the usage of media from :pages: :places.',
        'pages' => '{0} no pages|{1} 1 page|[2,*] :count pages',
        'places' => '{0} no files in use|{1} 1 place with a file|[2,*] :count places with a file',
    ],
];
