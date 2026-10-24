<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Every CMS route is registered under this prefix and middleware group.
    |
    */

    'routes' => [
        'prefix' => 'cms',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Editors are CMS accounts of their own (the cms_editors table). They log
    | in through this session guard, which the package registers next to the
    | guards of the host. Logging in to the CMS never logs in to the site.
    | A remembered editor stays logged in for remember_days.
    |
    */

    'auth' => [
        'guard' => 'made-cms',
        'remember_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login codes
    |--------------------------------------------------------------------------
    |
    | The one-time code an editor receives by e-mail to log in. After
    | max_attempts wrong tries the code stops working.
    |
    */

    'login_code' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | The login mail goes out through the host's mailer and from address,
    | under the sender name below. With a help_url, it links to help with
    | logging in.
    |
    */

    'mail' => [
        'from_name' => 'Made CMS',
        'help_url' => env('MADE_CMS_HELP_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Editors who log in with a password can add an authenticator app. The
    | window is the number of 30 second periods a code may lag behind or
    | run ahead; recovery_codes is how many codes they get to save.
    |
    */

    'two_factor' => [
        'window' => 1,
        'recovery_codes' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Launcher
    |--------------------------------------------------------------------------
    |
    | The MADE tile in the bottom-right corner of every web page that opens
    | the CMS login panel. It is only shown to remembered editors and to
    | visitors from the allowed IP addresses (comma separated, CIDR allowed).
    | Showing the tile never grants access to the CMS.
    |
    */

    'launcher' => [
        'enabled' => filter_var(env('MADE_CMS_LAUNCHER_ENABLED', true), FILTER_VALIDATE_BOOL),
        'allowed_ips' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('MADE_CMS_LAUNCHER_IPS', '')),
        ))),
        'locale' => (string) env('MADE_CMS_LOCALE', 'nl'),
        'except' => ['nova*', 'telescope*', 'horizon*', 'pulse*'],
    ],

];
