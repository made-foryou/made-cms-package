{{--
    "Verwijderen" in the side column of the media library, for the chosen file or for the files the editor selected
    (design 13.1bv–cg): the button with the reason it cannot below it, and the confirmation that opens inline. library.js
    fills in the texts; the slots inside are named details-delete-*, the part itself $slot.
--}}
@php($l = static fn (string $key): string => (string) __('made-cms-package::media.library.'.$key, [], \Made\CmsPackage\Support\Locale::launcher()))
<div class="bibliotheek-wis" data-slot="{{ $slot }}"@if ($slot === 'details-selection-delete') hidden @endif>
    <button type="button" class="tekstlink" aria-expanded="false" aria-controls="{{ $id }}-vraag" aria-describedby="{{ $id }}-noot" data-slot="details-delete-button">{{ $l('delete') }}</button>
    <span class="bibliotheek-klein" id="{{ $id }}-noot" data-slot="details-delete-note"></span>
    <span class="bibliotheek-klein fout" role="alert" tabindex="-1" data-slot="details-delete-kept" hidden></span>
    <div class="bibliotheek-wisvraag" id="{{ $id }}-vraag" role="group" aria-labelledby="{{ $id }}-tekst" data-slot="details-delete-ask" hidden>
        <span class="bibliotheek-wistekst" id="{{ $id }}-tekst" data-slot="details-delete-text"></span>
        <span class="bibliotheek-klein">{{ $final }}</span>
        <span class="bibliotheek-klein fout" role="alert" data-slot="details-delete-error" hidden></span>
        <div class="bibliotheek-wisrij">
            <button type="button" class="knop gevaar" data-slot="details-delete-yes"></button>
            <button type="button" class="tekstlink" data-slot="details-delete-no">{{ $l('delete_no') }}</button>
        </div>
    </div>
</div>
