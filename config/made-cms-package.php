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

];
