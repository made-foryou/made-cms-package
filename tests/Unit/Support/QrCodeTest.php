<?php

declare(strict_types=1);

use Made\CmsPackage\Support\QrCode;

it('draws the url as an inline svg without an xml prolog', function (): void {
    $svg = QrCode::svg('otpauth://totp/TTV:menno?secret=JBSWY3DPEHPK3PXP&issuer=TTV');

    expect($svg)->toStartWith('<svg')
        ->toEndWith('</svg>')
        ->not->toContain('<?xml')
        ->not->toContain('<script');
});
