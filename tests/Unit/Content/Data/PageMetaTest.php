<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Data\ShareImage;

it('gives the meta of a page as an array for a prop', function (): void {
    $meta = new PageMeta(
        title: 'Onze teams · TTV Merwestad Dordrecht',
        description: null,
        canonical: 'https://ttvmerwestad.nl/teams',
        indexable: false,
    );

    expect($meta->toArray())->toBe([
        'title' => 'Onze teams · TTV Merwestad Dordrecht',
        'description' => null,
        'canonical' => 'https://ttvmerwestad.nl/teams',
        'indexable' => false,
        'image' => null,
        'site_name' => null,
    ]);
});

it('gives the image of a shared link and the name of the site along', function (): void {
    $meta = new PageMeta(
        title: 'Over ons · Example',
        description: 'Wie we zijn.',
        canonical: 'https://example.com/over-ons',
        indexable: true,
        image: new ShareImage('https://example.com/storage/deel.webp', 1200, 630, 'Spelers na de winst'),
        siteName: 'Example',
    );

    expect($meta->toArray())->toBe([
        'title' => 'Over ons · Example',
        'description' => 'Wie we zijn.',
        'canonical' => 'https://example.com/over-ons',
        'indexable' => true,
        'image' => ['url' => 'https://example.com/storage/deel.webp', 'width' => 1200, 'height' => 630, 'alt' => 'Spelers na de winst'],
        'site_name' => 'Example',
    ]);
});
