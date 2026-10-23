<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The mail with the login code and the link that logs in the browser that asked for it.
 * It is sent immediately and never implements ShouldQueue: the plain code must not end up in a queue.
 */
final class LoginCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $url,
        public readonly int $minutes,
        public readonly string $site,
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
        return (new MailMessage)
            ->subject($this->text('subject'))
            ->greeting($this->text('greeting'))
            ->line($this->text('code', ['code' => $this->code]))
            ->action($this->text('action'), $this->url)
            ->line($this->text('expires', ['minutes' => (string) $this->minutes]))
            ->line($this->text('ignore'));
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::mail.login_code.'.$key, ['site' => $this->site, ...$replace]);
    }
}
