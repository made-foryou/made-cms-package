@php
    $t = fn (string $key, array $replace = []): string => __('made-cms-package::launcher.'.$key, $replace, $locale);
    $mode = 'panel';
@endphp
<made-cms-launcher lang="{{ $locale }}" data-view="{{ $view }}"@if ($arrived) data-arrived @endif data-font-heading="{{ $fonts['heading'] }}" data-font-text="{{ $fonts['text'] }}" data-edit-script="{{ $editScript }}"><template shadowrootmode="open" shadowrootclonable>
<link rel="stylesheet" href="{{ $stylesheet }}"@if ($nonce) nonce="{{ $nonce }}"@endif>
<div class="plek" hidden>
    <button type="button" class="beheer" aria-label="{{ $t('open') }}" aria-haspopup="dialog">
        <span class="label" aria-hidden="true">
            <span class="label-titel">{{ $t('title') }}</span>
            <span class="label-product">{{ $t('product') }}</span>
            <span class="streep"></span>
        </span>
        <svg class="beeldmerk" viewBox="0 0 120 120" width="48" height="48" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="34" fill="#0e2b26"/><path d="M45 92 L75 28" fill="none" stroke="#be8a1c" stroke-width="21" stroke-linecap="round"/></svg>
    </button>
</div>
<dialog class="paneel" aria-labelledby="made-cms-titel">
    <div class="inhoud">
        @include('made-cms-package-launcher::partials.head', ['closable' => true])
        @include('made-cms-package-launcher::partials.flow')
        <p class="voet">{{ $t('no_access') }}</p>
    </div>
</dialog>
@include('made-cms-package-launcher::partials.edit-bar')
@include('made-cms-package-launcher::partials.outlines')
</template></made-cms-launcher>
<script type="module" src="{{ $script }}"@if ($nonce) nonce="{{ $nonce }}"@endif></script>
