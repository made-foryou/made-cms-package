<?php

declare(strict_types=1);

use Made\CmsPackage\Http\Cms\Words;

it('lists words as one phrase, the last two joined by the word it gets', function (): void {
    expect(Words::list(['Over ons'], 'en'))->toBe('Over ons')
        ->and(Words::list(['Over ons', 'Jeugd'], 'en'))->toBe('Over ons en Jeugd')
        ->and(Words::list(['jpg', 'png', 'webp'], 'of'))->toBe('jpg, png of webp')
        ->and(Words::list([], 'en'))->toBe('');
});
