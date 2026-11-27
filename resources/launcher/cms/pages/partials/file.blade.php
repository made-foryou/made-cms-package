{{-- One file of the card "Bestanden op deze pagina": its thumbnail, or the icon of its kind (a document as paper), and its name. --}}
<span class="paginabestand-beeld" data-kind="{{ $file['kind'] }}">
    @if ($file['thumb'] !== null)
        <img src="{{ $file['thumb'] }}" alt="" loading="lazy" decoding="async">
    @elseif ($file['kind'] === 'document')
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5"/><path d="M10 17h5"/></svg>
    @elseif ($file['kind'] === 'video')
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>
    @else
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
    @endif
    @if ($file['noAlt'])
        <span class="beeldkaart-stip" role="img" aria-label="{{ $t('pages.show.files.no_alt') }}"></span>
    @endif
</span>
<span class="paginabestand-naam">{{ $file['name'] }}</span>
