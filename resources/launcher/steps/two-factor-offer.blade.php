<div class="stap" data-form="offer">
    <span class="slot-icoon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/></svg></span>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('offer.heading') }}</h2>
        <p>{{ $t('offer.intro') }}</p>
        <p class="klein">{{ $t('offer.hint') }}</p>
    </div>
    <div class="acties">
        <button type="button" class="knop" data-actie="setup"><span>{{ $t('offer.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="done">{{ $t('offer.later') }}</button>
    </div>
</div>
