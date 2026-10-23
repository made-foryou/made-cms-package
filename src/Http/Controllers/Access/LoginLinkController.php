<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The link in the login mail.
 */
final class LoginLinkController
{
    public function __invoke(string $token): never
    {
        throw new NotFoundHttpException;
    }
}
