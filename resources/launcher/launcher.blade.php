@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::launcher.'.$key, $replace, $locale);
@endphp
<made-cms-launcher lang="{{ $locale }}" data-font-heading="{{ $fonts['heading'] }}" data-font-text="{{ $fonts['text'] }}"><template shadowrootmode="open" shadowrootclonable>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
<div class="plek" hidden>
    <button type="button" class="beheer" aria-label="{{ $t('open') }}" aria-haspopup="dialog">
        <span class="label" aria-hidden="true">
            <span class="label-titel">{{ $t('title') }}</span>
            <span class="label-product">{{ $t('product') }}</span>
            <span class="streep"></span>
        </span>
        <svg class="beeldmerk" viewBox="0 0 120 120" width="48" height="48" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="34" fill="#0e2b26"/><path d="M45 92 L75 28" fill="none" stroke="#be8a1c" stroke-width="21" stroke-linecap="round"/></svg>
    </button>
</div>
<dialog class="paneel" aria-labelledby="made-cms-titel">
    <div class="inhoud">
        <div class="kop">
            <span class="productlogo" role="img" aria-label="{{ $t('product') }}">
                <svg class="woordmerk" viewBox="0 0 495 100" width="130" height="26" aria-hidden="true" focusable="false"><g fill="none" stroke-width="22" stroke-linecap="round" stroke-linejoin="round"><path class="woord" d="M11 89 L11 11 L48 57 L85 11 L85 89"/><path class="woord" d="M123 89 L155 11 L187 89"/><path class="woord" d="M133 65 L177 65"/><path class="woord" d="M313 89 L313 11 L346 11 C369 11 380 29 380 50 C380 71 369 89 346 89 Z"/><path class="woord" d="M484 11 L418 11 L418 89 L484 89"/><path class="woord" d="M418 50 L470 50"/><path class="slash" d="M236 89 L264 11"/></g></svg>
                <span class="scheiding" aria-hidden="true"></span>
                <span class="product" aria-hidden="true">CMS</span>
            </span>
            <span class="kop-rechts">
                <span class="sitelabel">{{ $site }}</span>
                <button type="button" class="sluit" aria-label="{{ $t('close') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
            </span>
        </div>
        <div class="stappen" role="img" aria-label="{{ $t('step', ['current' => 1, 'total' => 3]) }}">
            <span class="stapbalk huidig"></span>
            <span class="stapbalk"></span>
            <span class="stapbalk"></span>
            <span class="staplabel" aria-hidden="true">{{ $t('step', ['current' => 1, 'total' => 3]) }}</span>
        </div>
        <form class="stap" method="dialog">
            <div class="tekst">
                <h2 id="made-cms-titel">{{ $t('email.heading') }}</h2>
                <p>{{ $t('email.intro', ['host' => $host]) }}</p>
            </div>
            <div class="veld">
                <label for="made-cms-email">{{ $t('email.label') }}</label>
                <input id="made-cms-email" type="email" name="email" autocomplete="username" placeholder="{{ $t('email.placeholder') }}" aria-describedby="made-cms-email-hint" required autofocus>
                <p id="made-cms-email-hint" class="hint">{{ $t('email.hint') }}</p>
            </div>
            <div>
                <button type="submit" class="knop">{{ $t('email.submit') }}</button>
            </div>
        </form>
        <p class="voet">{{ $t('no_access') }}</p>
    </div>
</dialog>
</template></made-cms-launcher>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
