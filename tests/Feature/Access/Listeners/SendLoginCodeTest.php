<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Made\CmsPackage\Access\Actions\IssueLoginCode;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;

beforeEach(function (): void {
    Notification::fake();
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad']);
});

function issueAndTerminate(): IssuedLoginCode
{
    $issued = IssueLoginCode::run(new LoginCodeRequest(email: 'menno@made-foryou.nl', binding: 'binding', returnTo: null));

    app()->terminate();

    return $issued;
}

it('mails the code to the address once the response is sent', function (): void {
    $issued = IssueLoginCode::run(new LoginCodeRequest(email: 'menno@made-foryou.nl', binding: 'binding', returnTo: null));

    Notification::assertNothingSent();

    app()->terminate();

    Notification::assertSentOnDemand(
        LoginCodeNotification::class,
        fn (LoginCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'menno@made-foryou.nl'
            && $notification->code === $issued->code,
    );
});

it('links to the configured site url, never to the host the request came in on', function (): void {
    $issued = issueAndTerminate();

    Notification::assertSentOnDemand(
        LoginCodeNotification::class,
        fn (LoginCodeNotification $notification): bool => $notification->url === 'https://ttvmerwestad.nl/cms/login/link/'.$issued->linkToken,
    );
});

it('writes the mail in the launcher language', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    issueAndTerminate();

    Notification::assertSentOnDemand(
        LoginCodeNotification::class,
        fn (LoginCodeNotification $notification): bool => $notification->locale === 'en',
    );
});

it('keeps the code out of the subject', function (): void {
    $issued = issueAndTerminate();

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use ($issued): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);

        expect($mail->subject)->toBe('Je inlogcode voor TTV Merwestad')
            ->not->toContain($issued->code)
            ->and(implode(' ', $mail->introLines))->toContain($issued->code)
            ->and($mail->actionUrl)->toBe($notification->url);

        return true;
    });
});
