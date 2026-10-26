<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

final class LoginLinkRejected extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $otherBrowser,
    ) {
        parent::__construct($message);
    }

    public static function expired(): self
    {
        return new self('The login link no longer works.', otherBrowser: false);
    }

    public static function otherBrowser(): self
    {
        return new self('The login link was opened in another browser than the one that asked for the code.', otherBrowser: true);
    }
}
