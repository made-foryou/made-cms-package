@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::mail.login_code.'.$key, $replace);
    $heading = "Nunito, 'Trebuchet MS', Arial, sans-serif";
    $body = "'Instrument Sans', 'Segoe UI', Helvetica, Arial, sans-serif";
    $mono = "ui-monospace, 'SF Mono', Menlo, Consolas, monospace";

    $date = $requestedAt->locale(app()->getLocale())->translatedFormat('j F');
    $from = null;

    if ($device['device'] !== null) {
        $from = __('made-cms-package::mail.devices.'.$device['device']);
        $from = $device['browser'] === null ? $from : $t('device_browser', ['device' => $from, 'browser' => $device['browser']]);
    }

    $requested = $from === null
        ? $t('requested', ['date' => $date, 'time' => $requestedAt->format('H:i')])
        : $t('requested_from', ['date' => $date, 'time' => $requestedAt->format('H:i'), 'device' => $from]);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $t('subject', ['code' => implode(' ', $halves), 'site' => $site]) }}</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600&amp;family=Nunito:wght@700&amp;display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background-color: #e9e6dc; -webkit-text-size-adjust: 100%;">
{{-- The preheader: the line mail apps show next to the subject. --}}
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">{{ $t('preheader', ['minutes' => $minutes]) }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #e9e6dc;">
<tr>
<td align="center" style="padding: 24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; background-color: #f3f1e8; border: 1px solid #dedbcd; border-radius: 16px; border-collapse: separate; overflow: hidden;">

    {{-- Product logo and site --}}
    <tr>
        <td style="padding: 26px 40px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="font-family: {{ $heading }}; font-size: 22px; font-weight: 700; letter-spacing: 0.04em; color: #0e2b26; white-space: nowrap;">
                        MA<span style="color: #be8a1c;">/</span>DE<span style="color: #6e7f78; font-weight: 400; padding: 0 10px;">|</span><span style="font-size: 15px; letter-spacing: 0.12em;">CMS</span>
                    </td>
                    <td align="right" style="font-family: {{ $body }}; font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: #47564f;">{{ $site }}</td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Greeting --}}
    <tr>
        <td style="padding: 34px 40px 0;">
            <h1 style="margin: 0 0 14px; font-family: {{ $heading }}; font-size: 28px; line-height: 1.2; font-weight: 700; letter-spacing: -0.015em; color: #0e2b26;">{{ $name === null ? $t('greeting') : $t('greeting_named', ['name' => $name]) }}</h1>
            <p style="margin: 0; font-family: {{ $body }}; font-size: 17px; line-height: 1.6; color: #47564f;">{{ $t('intro') }}</p>
        </td>
    </tr>

    {{-- The code, in two halves with the slash like the boxes in the panel --}}
    <tr>
        <td style="padding: 26px 40px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #fbfaf5; border: 1px solid #dedbcd; border-radius: 16px; border-collapse: separate;">
                <tr>
                    <td align="center" style="padding: 26px 28px;">
                        <p style="margin: 0 0 10px; font-family: {{ $body }}; font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: #6e7f78;">{{ $t('label') }}</p>
                        <p aria-label="{{ $t('code_label', ['code' => implode(' ', $halves)]) }}" style="margin: 0 0 10px; font-family: {{ $heading }}; font-size: 48px; line-height: 1; font-weight: 700; letter-spacing: 0.12em; color: #0e2b26;">{{ $halves[0] }}<span aria-hidden="true" style="color: #be8a1c; padding: 0 6px;">/</span>{{ $halves[1] }}</p>
                        <p style="margin: 0; font-family: {{ $body }}; font-size: 14px; line-height: 1.5; color: #47564f;">{{ $t('expires', ['minutes' => $minutes, 'time' => $expiresAt->format('H:i')]) }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- The button --}}
    <tr>
        <td align="center" style="padding: 26px 40px 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="background-color: #be8a1c; border-radius: 10px;">
                        <a href="{{ $url }}" style="display: inline-block; padding: 16px 28px; font-family: {{ $heading }}; font-size: 16px; line-height: 1; font-weight: 700; color: #0e2b26; text-decoration: none; border-radius: 10px;">{{ $t('action', ['site' => $site]) }}</a>
                    </td>
                </tr>
            </table>
            <p style="margin: 14px 0 0; font-family: {{ $body }}; font-size: 13px; line-height: 1.5; color: #47564f; text-align: center;">
                {{ $t('fallback') }}<br>
                <a href="{{ $url }}" style="font-family: {{ $mono }}; font-size: 12px; color: #0e2b26; word-break: break-all;">{{ $url }}</a>
            </p>
        </td>
    </tr>

    {{-- Not you? --}}
    <tr>
        <td style="padding: 34px 40px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1.5px solid #dedbcd; border-radius: 10px; border-collapse: separate;">
                <tr>
                    <td valign="top" style="padding: 16px 0 16px 20px; width: 14px; font-family: {{ $heading }}; font-size: 22px; line-height: 1; font-weight: 700; color: #be8a1c;" aria-hidden="true">/</td>
                    <td style="padding: 18px 20px 18px 10px; font-family: {{ $body }}; font-size: 14px; line-height: 1.55; color: #47564f;"><strong style="font-weight: 600; color: #0e2b26;">{{ $t('warning_title') }}</strong> {{ $t('warning') }}</td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- When and from where --}}
    <tr>
        <td style="padding: 26px 40px 0; font-family: {{ $body }}; font-size: 14px; line-height: 1.55; color: #47564f;">{{ $requested }}</td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding-top: 34px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0e2b26;">
                <tr>
                    <td style="padding: 22px 40px 26px; font-family: {{ $body }}; font-size: 13px; line-height: 1.5; color: #a9b6b1;">
                        <p style="margin: 0 0 8px; font-family: {{ $heading }}; font-size: 14px; font-weight: 700; color: #f3f1e8;">Made CMS</p>
                        <p style="margin: 0 0 8px;">{{ $t('footer_reason', ['site' => $site]) }}</p>
                        <p style="margin: 0;">{{ $t('footer_built') }} <a href="https://made-foryou.nl" style="color: #d9a63a; text-decoration: underline;">MADE</a> {{ $t('footer_people') }}@if (is_string($helpUrl) && $helpUrl !== '') <a href="{{ $helpUrl }}" style="color: #d9a63a; text-decoration: underline;">{{ $t('help') }}</a>@endif</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</td>
</tr>
</table>
</body>
</html>
