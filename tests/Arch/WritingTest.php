<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\GenerateStripTypes;

arch('only GenerateStripTypes writes files into the site')
    ->expect('Made\CmsPackage')
    ->not->toUse(['fopen', 'fwrite', 'file_put_contents', 'mkdir', 'rename', 'copy', 'unlink', 'Illuminate\Filesystem'])
    ->ignoring(GenerateStripTypes::class);

// The files of the media library reach a disk only through the Actions that check them (UploadMedia), never from Http.
arch('only the content actions use a storage disk')
    ->expect('Made\CmsPackage')
    ->not->toUse('Illuminate\Support\Facades\Storage')
    ->ignoring('Made\CmsPackage\Content\Actions');

arch('the scan builds code but never writes it')
    ->expect('Made\CmsPackage\Content\Scan')
    ->not->toUse(['fopen', 'fwrite', 'file_put_contents', 'mkdir', 'Illuminate\Filesystem']);
