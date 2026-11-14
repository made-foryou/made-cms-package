{{--
    The upload field: one field with five moments (at rest, dragging, busy, done, an error per file), the same in the
    media library, the picker and the edit shell. A host includes it with $upload = UploadField::make($folder), or
    UploadField::image($folder) for the field of an image (one photo at a time), and loads $upload['script']. Its
    fixed texts are here; upload.js fills in the rest from data-config.
--}}
@php($t = $upload['t'])
<section class="upload" data-upload data-moment="rest"@if ($upload['one_image']) data-one-image @endif data-config="{{ json_encode($upload['config'], JSON_THROW_ON_ERROR) }}" aria-label="{{ $t('label') }}">
    <div class="upload-vlak" data-slot="upload-zone">
        <span class="upload-icoon" aria-hidden="true">
            <svg data-slot="upload-icon-rest" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"></path><path d="M6 10l6-6 6 6"></path><path d="M4 20h16"></path></svg>
            <svg data-slot="upload-icon-drag" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M12 4v12"></path><path d="M6 10l6 6 6-6"></path><path d="M4 20h16"></path></svg>
        </span>
        <strong class="upload-titel" data-slot="upload-title">{{ $t('title') }}</strong>
        <span data-slot="upload-choose-line">{{ $t('or') }} <button type="button" class="tekstlink" data-slot="upload-choose">{{ $t('choose') }}</button></span>
        <span class="upload-klein" data-slot="upload-rules">{{ $upload['rules'] }}</span>
        <strong class="upload-titel" data-slot="upload-drag-title" hidden>{{ $t('drop') }}</strong>
        <span data-slot="upload-drag-line" hidden></span>
        <input class="sr-only" type="file" @unless ($upload['one_image']) multiple @endunless accept="{{ $upload['accept'] }}" tabindex="-1" aria-hidden="true" data-slot="upload-input">
    </div>

    <p class="upload-kop" data-slot="upload-done" hidden>
        <span class="upload-vinkje" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"></path></svg></span>
        <span data-slot="upload-done-text"></span>
    </p>

    <ul class="upload-lijst" aria-label="{{ $t('list') }}" data-slot="upload-list" hidden></ul>

    <p class="upload-klein" role="status" data-slot="upload-summary" hidden></p>
    <p class="upload-klein" data-slot="upload-errors-note" hidden><span data-slot="upload-errors-others">{{ $t('errors_others') }} </span>{{ $t('errors_note') }}</p>

    <div class="upload-acties" data-slot="upload-actions" hidden>
        <button type="button" class="knop" data-slot="upload-save">{{ $t('save_alts') }}</button>
        <button type="button" class="tekstlink" data-slot="upload-later">{{ $t('later') }}</button>
        <span class="upload-klein" data-slot="upload-later-hint">{{ $t('later_hint') }}</span>
        <button type="button" class="knop" data-slot="upload-close" hidden>{{ $t('close') }}</button>
    </div>
    <p class="upload-klein" role="status" data-slot="upload-save-status" hidden></p>

    <p class="sr-only" aria-live="polite" data-slot="upload-announce"></p>

    {{-- One file of the field. Its state (waiting, uploading, processing, done, error) decides which parts show. --}}
    <template data-upload-row>
        <li class="upload-rij" data-state="waiting">
            <div class="upload-rij-kop">
                <span class="upload-mini" aria-hidden="true" data-slot="upload-row-mini">
                    <svg data-slot="upload-row-icon-image" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"></rect><circle cx="9" cy="10" r="2"></circle><path d="M21 16l-5-5-8 8"></path></svg>
                    <svg data-slot="upload-row-icon-file" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" hidden><path d="M7 3h7l5 5v13H7z"></path><path d="M14 3v5h5"></path></svg>
                    <img alt="" data-slot="upload-row-thumb" hidden>
                </span>
                <span class="upload-kruis" aria-hidden="true" data-slot="upload-row-cross" hidden><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"></path></svg></span>
                <span class="upload-rij-tekst">
                    <span class="upload-naam" data-slot="upload-row-name"></span>
                    <span class="upload-klein" data-slot="upload-row-note"></span>
                    <span class="veld upload-alt" data-slot="upload-row-alt" hidden>
                        <label data-slot="upload-row-alt-label">{{ $t('alt') }}</label>
                        <input type="text" maxlength="255" autocomplete="off" placeholder="{{ $t('alt_placeholder') }}" data-slot="upload-row-alt-input">
                        <span class="upload-klein" data-slot="upload-row-alt-hint"@unless ($upload['one_image']) hidden @endunless>{{ $upload['one_image'] ? $t('alt_hint') : '' }}</span>
                        <span class="veld-fout" data-slot="upload-row-alt-error" hidden></span>
                    </span>
                </span>
                <span class="upload-procent" data-slot="upload-row-percent" hidden></span>
                <button type="button" class="actie klein" data-slot="upload-row-cancel" hidden>{{ $t('cancel') }}</button>
                <button type="button" class="actie klein" data-slot="upload-row-retry" hidden>{{ $t('retry') }}</button>
                <button type="button" class="actie klein" data-slot="upload-row-dismiss" hidden>{{ $t('dismiss') }}</button>
            </div>
            <div class="upload-balk" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-slot="upload-row-bar" hidden><span></span></div>
        </li>
    </template>
</section>
