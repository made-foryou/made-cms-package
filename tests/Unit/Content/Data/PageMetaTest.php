<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PageMeta;

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
    ]);
});
