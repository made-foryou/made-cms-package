<div class="stap" data-form="done">
    <span class="vinkcirkel" aria-hidden="true"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path class="vink" d="M5 12l5 5 9-10"/></svg></span>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('done.heading') }}</h2>
        <p><span data-if="!secured">{{ $t('done.intro') }}</span><span data-if="secured">{{ $t('done.intro_secured') }}</span></p>
    </div>
    <div class="acties">
        <button type="button" class="knop" data-actie="finish"><span>{{ $t('done.submit') }}</span></button>
    </div>
</div>
