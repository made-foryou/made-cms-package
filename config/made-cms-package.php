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
    |
    */

    'auth' => [
        'guard' => 'made-cms',
    ],

    /*
    |--------------------------------------------------------------------------
    | Login codes
    |--------------------------------------------------------------------------
    |
    | The one-time code an editor receives by e-mail to log in.
    |
    */

    'login_code' => [
        'length' => 6,
        'ttl_minutes' => 10,
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
