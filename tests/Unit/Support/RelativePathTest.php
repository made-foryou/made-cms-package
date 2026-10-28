<?php

declare(strict_types=1);

use Made\CmsPackage\Support\RelativePath;

it('walks from a folder of the host to a file of the package', function (): void {
    expect(RelativePath::between('/var/www/site/resources/cms/strips', '/var/www/site/vendor/made-foryou/made-cms-package/resources/schema/strip.json'))
        ->toBe('../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json');
});

it('points at a file in the same folder by its name', function (): void {
    expect(RelativePath::between('/var/www/site/schema/', '/var/www/site/schema/strip.json'))->toBe('strip.json');
});

it('accepts windows separators', function (): void {
    expect(RelativePath::between('C:\\sites\\ttv\\resources\\cms\\strips', 'C:\\sites\\ttv\\vendor\\made-foryou\\made-cms-package\\resources\\schema\\strip.json'))
        ->toBe('../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json');
});
