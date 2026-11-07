<?php

declare(strict_types=1);

return [
    'strip' => 'strip',
    'keys' => [
        'heading' => 'heading',
        'text' => 'label',
        'paragraph' => 'text',
        'rich_text' => 'content',
        'list' => 'list',
        'button' => 'button',
        'link' => 'link',
        'image' => 'image',
        'icon' => 'icon',
        'items' => 'items',
    ],
    'doubts' => [
        'generic_name' => 'No id or recognisable class: the name is generic.',
        'contains_form' => 'Holds a form; its fields were not taken over.',
        'decorative_image' => 'An image without alt text or hidden from screen readers: perhaps decoration.',
        'background_image' => 'A background image from the style of the element.',
        'icon_choice' => 'A choice guessed from the icons of the items.',
        'items_differ' => 'The items are not all built exactly alike.',
        'nested_list' => 'A list inside an item: one value per line.',
        'same_in_every_item' => 'The same in every item: perhaps fixed text of the design.',
    ],
    'generate' => [
        'left_out' => ':field: waits for the image field type.',
    ],
];
