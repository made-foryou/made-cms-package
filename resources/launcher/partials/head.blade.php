<div class="kop">
    @include('made-cms-package-launcher::partials.productlogo', ['label' => $t('product')])
    <span class="kop-rechts">
        <span class="sitelabel">{{ $site }}</span>
        @if ($closable ?? false)
            <button type="button" class="sluit" aria-label="{{ $t('close') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
        @endif
    </span>
</div>
