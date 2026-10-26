<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Made\CmsPackage\Access\Actions\IssueLoginCode;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;

beforeEach(function (): void {
    Notification::fake();
    config([
        'app.url' => 'https://ttvmerwestad.nl',
        'app.name' => 'TTV Merwestad',
        'mail.from' => ['address' => 'inloggen@example.com', 'name' => 'TTV Merwestad'],
    ]);
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
        fn (LoginCodeNotification $notification): bool => $notification->url === 'https://ttvmerwestad.nl/made-cms/login/link/'.$issued->linkToken,
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

it('puts the code in the subject, split in two halves like in the panel', function (): void {
    $issued = issueAndTerminate();
    [$first, $second] = str_split($issued->code, 3);

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use ($first, $second): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);

        expect($mail->subject)->toBe("{$first} {$second} is je inlogcode voor TTV Merwestad")
            ->and($mail->from)->toBe(['inloggen@example.com', 'Made CMS']);

        return true;
    });
});

it('renders the mail of the design: greeting, code, button, expiry and where it was asked for', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 24)->setTime(11, 24));
    Editor::factory()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno Tempelaar']);

    $issued = IssueLoginCode::run(new LoginCodeRequest(
        email: 'menno@made-foryou.nl',
        binding: 'binding',
        returnTo: null,
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
    ));
    app()->terminate();
    [$first, $second] = str_split($issued->code, 3);

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use ($first, $second, $issued): bool {
        $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

        expect($html)->toContain('Hoi Menno, hier is je inlogcode.')
            ->toContain($first)->toContain($second)
            ->toContain('Werkt 10 minuten, tot 11:34.')
            ->toContain('Log direct in op TTV Merwestad')
            ->toContain('href="https://ttvmerwestad.nl/made-cms/login/link/'.$issued->linkToken.'"')
            ->toContain('Aangevraagd op 24 oktober om 11:24 vanaf een Mac (Safari).')
            ->toContain('Vroeg jij deze code niet aan?')
            ->toContain('Werkt 10 minuten. Vroeg jij hem niet aan? Negeer deze mail.')
            ->not->toContain('Hulp bij inloggen');

        return true;
    });
});

it('sends a plain text version along', function (): void {
    $issued = issueAndTerminate();

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use ($issued): bool {
        $mail = $notification->toMail(new AnonymousNotifiable);
        $text = view($mail->view['text'], $mail->data())->render();

        expect($text)->toContain('hier is je inlogcode voor TTV Merwestad: '.substr($issued->code, 0, 3).' '.substr($issued->code, 3))
            ->toContain('https://ttvmerwestad.nl/made-cms/login/link/'.$issued->linkToken)
            ->not->toContain('<');

        return true;
    });
});

it('greets without a name and leaves out what it does not know', function (): void {
    issueAndTerminate();

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification): bool {
        $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

        expect($html)->toContain('Hoi, hier is je inlogcode.')
            ->not->toContain(' vanaf ');

        return true;
    });
});

it('links to help with logging in when the site has a help page', function (): void {
    config(['made-cms-package.mail.help_url' => 'https://made-foryou.nl/hulp']);

    issueAndTerminate();

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification): bool {
        expect((string) $notification->toMail(new AnonymousNotifiable)->render())
            ->toContain('href="https://made-foryou.nl/hulp"')
            ->toContain('Hulp bij inloggen');

        return true;
    });
});
