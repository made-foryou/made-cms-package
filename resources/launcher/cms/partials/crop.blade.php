{{--
    The crop step of a photo (design 13.6, 14.6): a modal dialog above the edit shell or a screen of the library. A host
    includes it once with $crop = CropStep::make($moves) and loads $crop['script']; the media element and the upload
    field open it with made-cms:crop on the dialog. Its slots are named crop-*.
--}}
@php($t = $crop['t'])
<dialog class="bijsnijden" data-media-crop aria-modal="true" aria-labelledby="made-cms-bijsnijden-kop" aria-describedby="made-cms-bijsnijden-regel" data-config="{{ json_encode($crop['config'], JSON_THROW_ON_ERROR) }}">
    <div class="kiezer-kop bijsnijden-kop">
        <div class="kiezer-titel">
            <span class="eyebrow" data-slot="crop-eyebrow"></span>
            <h2 id="made-cms-bijsnijden-kop">{{ $t('title') }}</h2>
            <span class="kiezer-voor" id="made-cms-bijsnijden-regel" data-slot="crop-line"></span>
            <span class="bijsnijden-gebruik" data-slot="crop-used" hidden></span>
        </div>
        <button type="button" class="kiezer-sluit" aria-label="{{ $t('close') }}" data-slot="crop-close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>

    <div class="bijsnijden-romp">
        <div class="bijsnijden-werk">
            <div class="bijsnijden-doek" data-slot="crop-stage">
                <div class="bijsnijden-beeld" data-slot="crop-picture" hidden>
                    <canvas aria-hidden="true" data-slot="crop-canvas"></canvas>
                    <div class="bijsnijden-kader" tabindex="0" role="group" aria-roledescription="{{ $t('frame') }}" aria-label="{{ $t('frame') }}" aria-describedby="made-cms-bijsnijden-kader-uitleg" data-slot="crop-frame">
                        @foreach (['nw', 'ne', 'sw', 'se'] as $corner)
                            <button type="button" class="bijsnijden-hoek" data-corner="{{ $corner }}" aria-label="{{ $t('corners.'.$corner) }}" aria-describedby="made-cms-bijsnijden-hoek-uitleg"></button>
                        @endforeach
                    </div>
                    <button type="button" class="bijsnijden-brandpunt" aria-label="{{ $t('focal') }}" aria-describedby="made-cms-bijsnijden-brandpunt-uitleg" data-slot="crop-focal"></button>
                    <span class="bijsnijden-maat" aria-hidden="true" data-slot="crop-area"></span>
                </div>
                <span class="bijsnijden-laden" data-slot="crop-loading"></span>
            </div>
            <div class="bijsnijden-gereedschap">
                <div class="bijsnijden-verhoudingen" role="group" aria-label="{{ $t('ratio') }}">
                    @foreach ($crop['ratios'] as $ratio)
                        <button type="button" class="filter" aria-pressed="false" data-ratio="{{ $ratio }}">{{ str_contains($ratio, ':') ? $ratio : $t('ratios.'.$ratio) }}</button>
                    @endforeach
                </div>
                <div class="bijsnijden-draaien">
                    <button type="button" class="bijsnijden-rond" aria-label="{{ $t('turn_left') }}" title="{{ $t('turn_left') }}" data-slot="crop-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg></button>
                    <button type="button" class="bijsnijden-rond" aria-label="{{ $t('turn_right') }}" title="{{ $t('turn_right') }}" data-slot="crop-right"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg></button>
                    <button type="button" class="bijsnijden-rond" aria-label="{{ $t('flip') }}" title="{{ $t('flip') }}" aria-pressed="false" data-slot="crop-flip"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3v18"/><path d="M8 7L3 12l5 5"/><path d="M16 7l5 5-5 5"/></svg></button>
                    <button type="button" class="actie" data-slot="crop-reset">{{ $t('reset') }}</button>
                </div>
            </div>
            <p class="veld-hint">{{ $t('hint') }}</p>
            <p class="veld-fout" role="alert" data-slot="crop-error" hidden></p>
            <p class="sr-only" id="made-cms-bijsnijden-kader-uitleg">{{ $t('frame_keys') }}</p>
            <p class="sr-only" id="made-cms-bijsnijden-hoek-uitleg">{{ $t('corner_keys') }}</p>
            <p class="sr-only" id="made-cms-bijsnijden-brandpunt-uitleg">{{ $t('focal_keys') }}</p>
        </div>

        <div class="bijsnijden-zij">
            <div class="bijsnijden-verklein">
                <label class="schakelrij">
                    <span class="schakel-label">{{ $t('scale_down') }}</span>
                    <input type="checkbox" class="schakel" role="switch" data-slot="crop-scale-down">
                </label>
                <p class="bijsnijden-gewicht" data-slot="crop-weight" hidden><s data-slot="crop-weight-from"></s><span class="bijsnijden-slash" aria-hidden="true">/</span><span data-slot="crop-weight-to"></span></p>
                <p class="veld-hint">{{ $t('scale_hint') }}</p>
            </div>
            <div class="veld">
                <label for="made-cms-bijsnijden-naam">{{ $t('name') }}</label>
                <input id="made-cms-bijsnijden-naam" type="text" maxlength="100" spellcheck="false" autocomplete="off" aria-describedby="made-cms-bijsnijden-naam-uitleg" data-slot="crop-name">
                <p class="veld-hint" id="made-cms-bijsnijden-naam-uitleg">{{ $t('name_hint') }}</p>
                <p class="veld-fout" data-slot="crop-name-error" hidden></p>
            </div>
            <div class="veld">
                <label for="made-cms-bijsnijden-alt">{{ $t('alt') }}</label>
                <input id="made-cms-bijsnijden-alt" type="text" maxlength="255" placeholder="{{ $t('alt_placeholder') }}" aria-describedby="made-cms-bijsnijden-alt-uitleg" data-slot="crop-alt">
                <p class="veld-hint" id="made-cms-bijsnijden-alt-uitleg">{{ $t('alt_hint') }}</p>
                <p class="veld-fout" data-slot="crop-alt-error" hidden></p>
            </div>
            @if ($crop['moves'])
                <div class="veld">
                    <label for="made-cms-bijsnijden-map">{{ $t('folder') }}</label>
                    <select id="made-cms-bijsnijden-map" data-slot="crop-folder"></select>
                    <p class="veld-fout" data-slot="crop-folder-error" hidden></p>
                </div>
            @endif
        </div>
    </div>

    <div class="kiezer-voet">
        <div class="bijsnijden-stappen" data-slot="crop-steps-row">
            <span class="stappen" aria-hidden="true" data-slot="crop-steps"></span>
            <span class="stapnoot" data-slot="crop-step"></span>
        </div>
        <div class="kiezer-knoppen">
            <span class="veld-hint" role="status" data-slot="crop-status"></span>
            <button type="button" class="tekstlink" data-slot="crop-keep">{{ $t('keep') }}</button>
            <button type="button" class="knop" data-slot="crop-save"></button>
        </div>
    </div>
    <p class="sr-only" aria-live="polite" data-slot="crop-announce"></p>
</dialog>
