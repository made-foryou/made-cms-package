<form class="stap" data-form="confirm" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('setup.heading') }}</h2>
        <p>{{ $t('setup.intro') }}</p>
    </div>
    <div class="qr-blok">
        <div class="qr" role="img" aria-label="{{ $t('setup.qr_label') }}" data-qr></div>
        <a class="knop secundair app-knop" href="#" data-otpauth><span>{{ $t('setup.open_app') }}</span></a>
    </div>
    <details class="sleutel-details">
        <summary>{{ $t('setup.manual') }}</summary>
        <p class="sleutel" data-slot="secret"></p>
        <button type="button" class="tekstlink klein" data-actie="copy-secret">{{ $t('setup.copy_key') }}</button>
    </details>
    @include('made-cms-package-launcher::partials.digits', [
        'field' => 'code',
        'legend' => $t('two_factor.legend'),
        'hint' => $t('setup.hint'),
        'length' => 6,
    ])
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('setup.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="done">{{ $t('offer.later') }}</button>
    </div>
</form>
