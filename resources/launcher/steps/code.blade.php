<form class="stap" data-form="verify" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('code.heading') }}</h2>
        <p>{{ $slots('code.intro', ['email']) }}</p>
    </div>
    @include('made-cms-package-launcher::partials.digits', [
        'field' => 'code',
        'legend' => $t('code.legend'),
        'hint' => $t('code.hint', ['minutes' => config('made-cms-package.login_code.ttl_minutes')]),
        'length' => config()->integer('made-cms-package.login_code.length'),
    ])
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('code.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="method">{{ $t('back') }}</button>
    </div>
</form>
