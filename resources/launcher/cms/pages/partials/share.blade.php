{{--
    The card "Hoe je link gedeeld wordt" (design 23, dark 24): a shared link as an app shows it (the image that is
    shared, the domain, and the title and the description from the card of Google), and the share image of the page,
    chosen from the media library or uploaded, with what to warn about. It saves on its own with a plain form and holds
    for the site at once; share.js follows the picker, the upload field and the crop step, and redraws the card.
--}}
@php
    [$sourceBefore, $sourceAfter] = explode(':link', $t('pages.show.share.source'), 2) + ['', ''];
    $file = $share['file'];
    $shown = $share['usable'] && $file !== null;
@endphp
<section class="kaart" id="made-cms-share" aria-labelledby="made-cms-share-kop">
    <div class="sectiekop">
        <h2 id="made-cms-share-kop">{{ $t('pages.show.share.heading') }}</h2>
        <span class="sectienoot">{{ $t('pages.show.share.caption') }}</span>
    </div>
    <form class="formulier" method="post" action="{{ $share['url'] }}" data-share data-config="{{ json_encode($share['config'], JSON_THROW_ON_ERROR) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="share_image" value="{{ $share['value'] }}" data-slot="share-value">

        <div class="deellink-blok">
            <figure class="deellink">
                <figcaption class="sr-only">{{ $t('pages.show.share.preview') }}</figcaption>
                <span class="deellink-beeld" data-slot="share-preview"@if ($share['preview'] === null) hidden @endif>
                    <img src="{{ $share['preview'] ?? '' }}" alt="" data-slot="share-preview-image">
                </span>
                <span class="deellink-tekst">
                    <span class="deellink-domein">{{ $share['domain'] }}</span>
                    <span class="deellink-titel" data-slot="share-title">{{ $share['title'] }}</span>
                    <span class="deellink-omschrijving" data-slot="share-description"@if ($share['description'] === null) hidden @endif>{{ $share['description'] }}</span>
                </span>
            </figure>
            <p class="deellink-noot">
                {{ $sourceBefore }}<a class="tekstlink" href="#made-cms-google">{{ $t('pages.show.share.source_link') }}</a>{{ $sourceAfter }}
                <span data-slot="share-no-description"@if ($share['description'] !== null) hidden @endif>{{ $t('pages.show.share.no_description') }}</span>
            </p>
            @unless ($share['indexable'])
                <p class="deellink-noot">{{ $t('pages.show.share.noindex') }}</p>
            @endunless
        </div>

        <div class="deelveld">
            <div class="deelveld-kop">
                <span class="deelveld-label" id="made-cms-share-label">{{ $t('pages.show.share.label') }}</span>
                <span class="sectienoot">{{ $t('pages.show.share.hint') }}</span>
            </div>
            <div class="deelveld-vak" role="group" aria-labelledby="made-cms-share-label">
                <div class="deelveld-bestand" data-slot="share-file"@if ($file === null) hidden @endif>
                    <span class="deelveld-mini" aria-hidden="true" data-slot="share-mini">
                        <img src="{{ $file['thumb'] ?? '' }}" alt="" data-slot="share-thumb">
                        <span class="deelveld-band eerste"></span>
                        <span class="deelveld-band tweede"></span>
                    </span>
                    <span class="deelveld-info">
                        <span class="deelveld-naam" data-slot="share-name">{{ $file === null ? '' : $file['name'].'.'.$file['extension'] }}</span>
                        <span class="sectienoot" data-slot="share-meta">{{ $file === null ? '' : ($file['folder'] === null ? $file['meta'] : $t('pages.show.share.meta', ['meta' => $file['meta'], 'folder' => $file['folder']])) }}</span>
                        <span class="deelveld-alt" data-slot="share-alt" data-alt="{{ ($file['alt'] ?? null) === null || trim((string) $file['alt']) === '' ? 'missing' : 'set' }}">
                            <span class="deelveld-alt-bol" role="img" aria-label="{{ $t('pages.show.share.no_alt') }}"></span>
                            <span data-slot="share-alt-text">{{ ($file['alt'] ?? null) === null || trim((string) $file['alt']) === '' ? $t('pages.show.share.no_alt') : $t('pages.show.share.alt', ['alt' => $file['alt']]) }}</span>
                        </span>
                    </span>
                </div>
                <div class="deelveld-leeg" data-slot="share-empty"@if ($file !== null) hidden @endif>
                    <span class="deelveld-leeg-vlak" aria-hidden="true"></span>
                    <span>{{ $t('pages.show.share.empty') }}</span>
                </div>
                <div class="deelveld-upload" data-slot="share-upload" hidden>
                    @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $share['upload']])
                </div>
                <div class="deelveld-knoppen">
                    <button type="button" class="actie klein" aria-haspopup="dialog" data-slot="share-pick">{{ $t('pages.show.share.pick') }}</button>
                    <button type="button" class="actie klein" data-slot="share-upload-button">{{ $t('pages.show.share.upload') }}</button>
                    <button type="button" class="actie klein" aria-haspopup="dialog" data-slot="share-crop"@unless ($shown && ($file['croppable'] ?? false)) hidden @endunless>{{ $t('pages.show.share.crop') }}</button>
                    <button type="button" class="deelveld-kruis" aria-label="{{ $t('pages.show.share.remove') }}" title="{{ $t('pages.show.share.remove') }}" data-slot="share-remove"@if ($file === null) hidden @endif>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                    <button type="button" class="tekstlink klein" data-slot="share-cancel" hidden>{{ $t('pages.show.share.cancel') }}</button>
                </div>
            </div>
            @error('share_image')
                <div class="melding alarm" role="alert">
                    <span class="bol alarm" aria-hidden="true"></span>
                    <span class="melding-tekst"><strong>{{ $t('pages.show.share.notices.failed_title') }}</strong> {{ $message }} {{ $t('pages.show.share.notices.failed') }}</span>
                </div>
            @enderror
            <div class="meldingen" data-slot="share-notices">
                @foreach ($share['notices'] as $notice)
                    <div class="melding{{ $notice['tone'] === '' ? '' : ' '.$notice['tone'] }}">
                        <span class="bol{{ $notice['tone'] === '' ? '' : ' '.$notice['tone'] }}" aria-hidden="true"></span>
                        <span class="melding-tekst">@if ($notice['title'] !== '')<strong>{{ $notice['title'] }}</strong> @endif{{ $notice['text'] }}@if ($notice['link'] !== null) <a class="tekstlink" href="{{ $notice['link']['href'] }}">{{ $notice['link']['text'] }} <span aria-hidden="true">→</span></a>@endif</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="deelvoet">
            <button type="submit" class="knop" data-slot="share-save" disabled>{{ $t('pages.show.save') }}</button>
            <span class="deelvoet-stand" data-slot="share-unsaved" hidden><span class="bol let" aria-hidden="true"></span>{{ $t('pages.show.share.unsaved') }}</span>
            <span class="sectienoot" data-slot="share-waiting" hidden>{{ $t('pages.show.share.waiting') }}</span>
            @if ($share['saved'])
                <span class="deelvoet-stand" role="status" data-slot="share-saved"><span class="bol goed" aria-hidden="true"></span>{{ $t('pages.show.share.saved') }}</span>
                <span class="sectienoot" data-slot="share-saved-text">{{ $t('pages.show.share.saved_text') }}</span>
            @endif
        </div>
    </form>
</section>
