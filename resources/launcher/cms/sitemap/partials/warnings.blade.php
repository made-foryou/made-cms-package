{{--
    The files of the site that go before those of the CMS (design 17.13), each with what the developer does about it
    and a button that copies that; cms.js shows the button, which a page without a script cannot use.
--}}
<section class="sitemapmeldingen" aria-label="{{ $t('sitemap.screen.warnings.label') }}">
    @foreach ($warnings as $warning)
        <div class="melding let sitemapmelding">
            <span class="bol let" aria-hidden="true"></span>
            <div class="melding-tekst">
                <strong class="sitemapmelding-titel">{{ $warning['title'] }}</strong>
                <span class="sitemapmelding-uitleg">{{ $warning['text'] }}</span>
                <div class="voor-developer">
                    <span class="voor-developer-kop">{{ $t('sitemap.screen.warnings.developer') }}</span>
                    <span class="voor-developer-tekst" data-copy-source>{{ $warning['instruction'] }}</span>
                    <span class="voor-developer-knoppen">
                        <button type="button" class="knop stil" data-copy data-copied="{{ $t('sitemap.screen.warnings.copied') }}" data-refused="{{ $t('sitemap.screen.warnings.copy_refused') }}" hidden>{{ $t('sitemap.screen.warnings.copy') }}</button>
                        <span class="voor-developer-status" role="status" data-copy-status></span>
                        <span class="voor-developer-noot">{{ $t('sitemap.screen.warnings.nothing_to_do') }}</span>
                    </span>
                </div>
            </div>
        </div>
    @endforeach
</section>
