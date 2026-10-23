<form class="stap" data-form="password" novalidate>
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1">{{ $t('password.heading') }}</h2>
        <p class="emailregel"><span class="emailpil" data-slot="email"></span><button type="button" class="tekstlink" data-actie="email">{{ $t('password.other_address') }}</button></p>
    </div>
    {{-- Lets password managers pair the password with the address of step 1. --}}
    <input class="sr-only" type="email" name="username" autocomplete="username" tabindex="-1" aria-hidden="true" data-slot-value="email">
    <div class="veld geheim" data-veld="password">
        <label for="made-cms-password">{{ $t('password.label') }}</label>
        <input id="made-cms-password" type="password" name="password" autocomplete="current-password" aria-describedby="made-cms-password-fout" required>
        <p id="made-cms-password-fout" class="melding" data-fout="password" hidden></p>
    </div>
    <div class="rij">
        <label class="vinkje"><input type="checkbox" name="remember" checked>{{ $t('password.remember', ['days' => config('made-cms-package.auth.remember_days')]) }}</label>
        <button type="button" class="tekstlink klein" data-actie="code">{{ $t('password.forgot') }}</button>
    </div>
    <div class="acties">
        <button type="submit" class="knop"><span>{{ $t('password.submit') }}</span></button>
        <button type="button" class="tekstlink klein" data-actie="method">{{ $t('back') }}</button>
    </div>
</form>
