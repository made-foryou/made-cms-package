<div class="kop">
    <span class="productlogo" role="img" aria-label="{{ $t('product') }}">
        <svg class="woordmerk" viewBox="0 0 495 100" width="130" height="26" aria-hidden="true" focusable="false"><g fill="none" stroke-width="22" stroke-linecap="round" stroke-linejoin="round"><path class="woord" d="M11 89 L11 11 L48 57 L85 11 L85 89"/><path class="woord" d="M123 89 L155 11 L187 89"/><path class="woord" d="M133 65 L177 65"/><path class="woord" d="M313 89 L313 11 L346 11 C369 11 380 29 380 50 C380 71 369 89 346 89 Z"/><path class="woord" d="M484 11 L418 11 L418 89 L484 89"/><path class="woord" d="M418 50 L470 50"/><path class="slash" d="M236 89 L264 11"/></g></svg>
        <span class="scheiding" aria-hidden="true"></span>
        <span class="product" aria-hidden="true">CMS</span>
    </span>
    <span class="kop-rechts">
        <span class="sitelabel">{{ $site }}</span>
        @if ($closable ?? false)
            <button type="button" class="sluit" aria-label="{{ $t('close') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
        @endif
    </span>
</div>
