<?php

declare(strict_types=1);

use Made\CmsPackage\Support\MediaLink;

it('builds the lasting link of a file from the configured url, never from the host of the request', function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl']);

    expect(MediaLink::url('01jbx3m5zq8w2p4r6t8v0x2z4b', 'wedstrijdschema'))
        ->toBe('https://ttvmerwestad.nl/made-cms/file/01jbx3m5zq8w2p4r6t8v0x2z4b/wedstrijdschema');
});

it('keeps the folder of a site that lives in a folder of its domain', function (): void {
    config(['app.url' => 'https://example.com/club/']);

    expect(MediaLink::url('01jbx3m5zq8w2p4r6t8v0x2z4b', 'wedstrijdschema'))
        ->toBe('https://example.com/club/made-cms/file/01jbx3m5zq8w2p4r6t8v0x2z4b/wedstrijdschema');
});
