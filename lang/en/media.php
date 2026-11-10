<?php

declare(strict_types=1);

return [
    'kinds' => [
        'image' => 'Image',
        'document' => 'Document',
        'video' => 'Video',
    ],

    'errors' => [
        'too_large' => 'Too large: a file may be :max.',
        'too_large_video' => 'Too large: a file may be :max. Put the video on YouTube or Vimeo and paste the link in a video block; that loads faster for visitors too.',
        'type_unknown' => 'The site does not know this type. Save it as :types and try again.',
        'svg_unsafe' => 'This svg holds code the site does not allow, such as a script or a link to elsewhere. Export it again as a plain drawing and try again.',
        'unreadable' => 'We cannot read this file. It may be damaged; save it again and try once more.',
        'alt_long' => 'An alt text may be at most :max characters.',
    ],

    'or' => 'or',

    'details' => [
        'dimensions' => ':width × :height',
        'kb' => ':size kB',
        'mb' => ':size MB',
        'decimal' => '.',
    ],
];
