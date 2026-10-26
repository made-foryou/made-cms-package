<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Made\CmsPackage\Support\Device;

/**
 * The mail with the login code and the link that logs in the browser that asked for it (design 1.10).
 * It is sent immediately and never implements ShouldQueue: the plain code must not end up in a queue.
 */
final class LoginCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $url,
        public readonly string $site,
        public readonly CarbonImmutable $requestedAt,
        public readonly CarbonImmutable $expiresAt,
        public readonly ?string $firstName,
        public readonly ?string $userAgent,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $halves = $this->halves();
        $message = (new MailMessage)
            ->subject((string) __('made-cms-package::mail.login_code.subject', ['code' => implode(' ', $halves), 'site' => $this->site]))
            ->view(
                ['html' => 'made-cms-package::mail.login-code', 'text' => 'made-cms-package::mail.login-code-text'],
                [
                    'site' => $this->site,
                    'halves' => $halves,
                    'url' => $this->url,
                    'name' => $this->firstName,
                    'minutes' => config()->integer('made-cms-package.login_code.ttl_minutes'),
                    'requestedAt' => $this->requestedAt,
                    'expiresAt' => $this->expiresAt,
                    'device' => Device::from($this->userAgent),
                    'helpUrl' => config('made-cms-package.mail.help_url'),
                ],
            );

        $from = config('mail.from.address');

        if (is_string($from) && $from !== '') {
            $message->from($from, config()->string('made-cms-package.mail.from_name', 'Made CMS'));
        }

        return $message;
    }

    /**
     * The code in two halves, like the boxes in the panel: 482 917.
     *
     * @return array{string, string}
     */
    private function halves(): array
    {
        $middle = intdiv(strlen($this->code), 2);

        return [substr($this->code, 0, $middle), substr($this->code, $middle)];
    }
}
