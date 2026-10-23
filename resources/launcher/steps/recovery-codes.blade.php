<div class="stap" data-form="codes">
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('codes.heading') }}</h2>
        <p>{{ $t('codes.intro') }}</p>
    </div>
    <ol class="herstelcodes" data-codes></ol>
    <div class="rij">
        <button type="button" class="tekstlink klein" data-actie="copy-codes">{{ $t('codes.copy') }}</button>
        <button type="button" class="tekstlink klein" data-actie="download-codes">{{ $t('codes.download') }}</button>
    </div>
    <div class="acties">
        <button type="button" class="knop" data-actie="saved"><span>{{ $t('codes.submit') }}</span></button>
    </div>
</div>
