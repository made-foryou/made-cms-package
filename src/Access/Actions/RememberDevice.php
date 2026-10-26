<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\KnownDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Decides what a device remembers after a login: enough to greet the editor next time, nothing that names them.
 */
final class RememberDevice
{
    use AsAction;

    public function handle(Editor $editor, LoginMethod $method): KnownDevice
    {
        return new KnownDevice(
            emailHash: $this->emailHash($editor->email),
            firstName: DescribeEditor::run($editor)->firstName,
            method: $method,
        );
    }

    /**
     * A keyed hash, so a device only recognises an address that is typed again.
     */
    public function emailHash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), config()->string('app.key'));
    }
}
