<form class="stap" data-form="recovery" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('recovery.heading') }}</h2>
        <p>{{ $t('recovery.intro') }}</p>
    </div>
    <div class="veld" data-veld="recovery_code">
        <label for="made-cms-recovery">{{ $t('recovery.label') }}</label>
        <input id="made-cms-recovery" type="text" name="recovery_code" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="made-cms-recovery-fout" required>
        <p id="made-cms-recovery-fout" class="melding" data-fout="recovery_code" hidden></p>
    </div>
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('code.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="two_factor">{{ $t('recovery.use_app') }}</button>
    </div>
</form>
