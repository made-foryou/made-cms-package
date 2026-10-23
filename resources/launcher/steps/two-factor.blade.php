<form class="stap" data-form="two_factor" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('two_factor.heading') }}</h2>
        <p>{{ $t('two_factor.intro', ['site' => $site]) }}</p>
    </div>
    @include('made-cms-package-launcher::partials.digits', [
        'field' => 'code',
        'legend' => $t('two_factor.legend'),
        'hint' => $t('two_factor.hint'),
        'length' => 6,
    ])
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('code.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="recovery">{{ $t('two_factor.use_recovery') }}</button>
    </div>
</form>
