<div class="stap" data-form="method">
    <div class="tekst">
        <h2 id="made-cms-titel" tabindex="-1"><span data-if="name">{{ $slots('method.heading_known', ['name']) }}</span><span data-if="!name">{{ $t('method.heading') }}</span></h2>
        <p><span data-if="name">{{ $t('method.intro_known') }}</span><span data-if="!name">{{ $slots('method.intro', ['email']) }}</span></p>
    </div>
    <div class="keuzes">
        <button type="button" class="keuze" data-methode="code">
            <span class="keuze-icoon accent"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 8l9 6 9-6"/></svg></span>
            <span class="keuze-tekst"><span class="keuze-titel">{{ $t('method.code.title') }}</span><span class="keuze-uitleg">{{ $t('method.code.text') }}</span></span>
            <span class="pil">{{ $t('method.code.badge') }}</span>
        </button>
        <button type="button" class="keuze" data-methode="password">
            <span class="keuze-icoon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M17 6l3 3"/><path d="M14 9l2 2"/></svg></span>
            <span class="keuze-tekst"><span class="keuze-titel">{{ $t('method.password.title') }}</span><span class="keuze-uitleg">{{ $t('method.password.text') }}</span></span>
        </button>
    </div>
    <p class="klein"><span data-if="name">{{ $slots('method.not_you', ['name']) }} </span><button type="button" class="tekstlink" data-actie="email">{{ $t('method.other_email') }}</button></p>
</div>
