<?php

declare(strict_types=1);

return [
    'button' => [
        'text' => 'Text',
        'link' => 'Goes to',
    ],
    'heading' => [
        'text' => 'Text',
        'tag' => 'Level',
        'tags' => [
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
            'span' => 'No heading',
        ],
        'descriptions' => [
            'h1' => 'The main heading of the page, one per page.',
            'h2' => 'A chapter: the heading of a strip.',
            'h3' => 'A part within a strip, such as a card.',
            'h4' => 'A heading below that again.',
            'h5' => 'Rarely needed; only below an H4.',
            'h6' => 'The lowest level; hardly ever needed.',
            'span' => 'Looks the same, but Google does not read it as a heading.',
        ],
    ],
    'validation' => [
        'required' => 'Fill in :attribute.',
        'string' => ':attribute must be text.',
        'array' => ':attribute does not have the right form.',
        'list' => ':attribute must be a list.',
        'in' => 'Choose one of the options for :attribute.',
        'uuid' => ':attribute does not have a valid id.',
        'max_string' => ':attribute may not be longer than :max characters.',
        'max_array' => ':attribute may not have more than :max.',
        'min_string' => ':attribute must be at least :min characters long.',
        'min_array' => ':attribute needs at least :min.',
        'link' => 'Choose a page or enter an address for :attribute.',
        'link_page' => 'The chosen page for :attribute does not exist.',
        'link_url' => 'The address for :attribute is invalid. Start with https://, mailto:, tel:, / or #.',
        'image' => 'Choose an image from Media for :attribute.',
        'image_missing' => 'The chosen image for :attribute no longer exists.',
        'image_kind' => 'The chosen file for :attribute is not an image.',
    ],
];
