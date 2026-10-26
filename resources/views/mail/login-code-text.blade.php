{{-- Plain text: nothing here is html, so nothing is escaped. --}}
@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::mail.login_code.'.$key, $replace);
    $code = implode(' ', $halves);
@endphp
{!! $name === null ? $t('text_greeting', ['site' => $site, 'code' => $code]) : $t('text_greeting_named', ['name' => $name, 'site' => $site, 'code' => $code]) !!}

{!! $t('text_link', ['minutes' => $minutes, 'time' => $expiresAt->format('H:i'), 'url' => $url]) !!}

{!! $t('text_warning') !!}
