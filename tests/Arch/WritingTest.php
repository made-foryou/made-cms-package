<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\GenerateStripTypes;

arch('only GenerateStripTypes writes files into the site')
    ->expect('Made\CmsPackage')
    ->not->toUse(['fopen', 'fwrite', 'file_put_contents', 'mkdir', 'rename', 'copy', 'unlink', 'Illuminate\Filesystem'])
    ->ignoring(GenerateStripTypes::class);

arch('the scan builds code but never writes it')
    ->expect('Made\CmsPackage\Content\Scan')
    ->not->toUse(['fopen', 'fwrite', 'file_put_contents', 'mkdir', 'Illuminate\Filesystem']);
