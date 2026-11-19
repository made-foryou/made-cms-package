<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Listeners;

use Illuminate\Support\Facades\Notification;
use Made\CmsPackage\Access\Actions\DescribeEditor;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * Mails the login code right after the response is sent: never through the queue, so the plain code
 * is never stored, and never during the request, so a slow mail server cannot reveal which addresses exist.
 */
final class SendLoginCode
{
    public function handle(LoginCodeIssued $event): void
    {
        $issued = $event->issued;
        $editor = Editor::query()->firstWithEmail($issued->loginCode->email);

        $notification = new LoginCodeNotification(
            code: $issued->code,
            url: Site::url(route('made-cms-package.login.link', ['token' => $issued->linkToken], false)),
            site: Site::name(),
            requestedAt: ($issued->loginCode->created_at ?? now())->toImmutable(),
            expiresAt: $issued->loginCode->expires_at,
            firstName: $editor === null ? null : DescribeEditor::run($editor)->firstName,
            userAgent: $issued->userAgent,
        );
        $notification->locale(Locale::launcher());

        // Terminating callbacks are not cleared after they ran, so a long-running process must not send twice.
        $sent = false;

        app()->terminating(function () use (&$sent, $issued, $notification): void {
            if ($sent) {
                return;
            }

            $sent = true;

            Notification::route('mail', $issued->loginCode->email)->notifyNow($notification);
        });
    }
}
