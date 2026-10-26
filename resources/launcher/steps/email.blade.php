<form class="stap" data-form="email" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('email.heading') }}</h2>
        <p>{{ $t('email.intro', ['site' => $site]) }}</p>
    </div>
    <div class="veld" data-veld="email">
        <label for="made-cms-email">{{ $t('email.label') }}</label>
        <input id="made-cms-email" type="email" name="email" autocomplete="username" placeholder="{{ $t('email.placeholder') }}" aria-describedby="made-cms-email-hint made-cms-email-fout" required data-slot-value="email">
        <p id="made-cms-email-hint" class="hint">{{ $t('email.hint') }}</p>
        <p id="made-cms-email-fout" class="melding" data-fout="email" hidden></p>
    </div>
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('email.submit') }}</span></button>
    </div>
</form>
