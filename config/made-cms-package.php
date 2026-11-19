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
        'prefix' => 'made-cms',
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

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | A deleted page can be restored for keep_deleted_days. After that the
    | made-cms:prune command, which runs every day, removes it for good.
    |
    */

    'pages' => [
        'keep_deleted_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta
    |--------------------------------------------------------------------------
    |
    | share_image is the image a shared link shows (og:image) for a page
    | without one of its own: an absolute address, or an address from the
    | root of the site such as /images/share.jpg. Give its width, height and
    | alt text when you know them; apps show a link sooner with the size.
    | Without a url a page without an image of its own is shared without one.
    |
    */

    'meta' => [
        'share_image' => [
            'url' => env('MADE_CMS_SHARE_IMAGE'),
            'width' => null,
            'height' => null,
            'alt' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap and robots.txt
    |--------------------------------------------------------------------------
    |
    | Route::madeCms() serves sitemap.xml and, in a group without a prefix,
    | robots.txt. robots holds the Disallow rules of the developer, the paths
    | per user agent, as Google reads them: * for any characters, $ for the
    | end. For example ['*' => ['/api/'], 'Googlebot' => ['/zoeken$']]. A
    | page a rule for * or Googlebot keeps away stays out of the sitemap.
    | Keep a page out of Google with its own switch instead: noindex.
    |
    */

    'sitemap' => [
        'robots' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strips
    |--------------------------------------------------------------------------
    |
    | Editors build pages from the strip types the site defines: one class per
    | type that extends StripType. The catalogue finds every class in path and
    | its sub-folders; namespace is the namespace that path maps to (PSR-4).
    |
    */

    'strips' => [
        'path' => app_path('Cms/Strips'),
        'namespace' => 'App\\Cms\\Strips',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Uploaded files are stored on disk, which must give a url (the public
    | disk after storage:link, or an S3 disk such as DigitalOcean Spaces).
    | Only the types in allowed_mimes up to max_upload_kb are accepted; the
    | type is read from the contents, never from the name. A photo is scaled
    | down to max_width and stored in format at quality with driver (gd or
    | imagick); the original is kept next to it. An svg and an animated gif
    | stay as they are. The original is written private and every other file
    | public, whatever the visibility of the disk.
    |
    */

    'media' => [
        'disk' => (string) env('MADE_CMS_MEDIA_DISK', 'public'),
        'max_upload_kb' => 25600,
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
            'application/pdf',
            'video/mp4',
        ],
        'image' => [
            'driver' => (string) env('MADE_CMS_IMAGE_DRIVER', 'gd'),
            'max_width' => 1920,
            'format' => 'webp',
            'quality' => 82,
        ],
    ],

];
