<div class="balk" role="region" aria-label="{{ $t('bar.label') }}" tabindex="-1">
    <div class="balk-links">
        <svg class="balk-merk" viewBox="0 0 120 120" width="28" height="28" aria-hidden="true" focusable="false"><rect width="120" height="120" rx="34" fill="#f3f1e8"/><path d="M45 92 L75 28" fill="none" stroke="#be8a1c" stroke-width="21" stroke-linecap="round"/></svg>
        <span class="balk-site">{{ $site }}</span>
        <span class="balk-slash" aria-hidden="true">/</span>
        <span class="balk-pagina">{{ $page }}</span>
    </div>
    <div class="balk-rechts">
        @if ($edit ?? null)<a class="balk-link" href="{{ $edit }}" data-edit{{ $editor === null ? ' hidden' : '' }}>{{ $t('bar.edit') }}</a>@endif
        <a class="balk-link" href="{{ $endpoints['start'] }}">{{ $t('bar.start') }}</a>
        <form class="uitloggen" method="post" action="{{ $endpoints['logout'] }}">
            @if ($view === 'edit_bar')<input type="hidden" name="_token" value="{{ csrf_token() }}">@endif
            <button type="submit" class="balk-link">{{ $t('bar.logout') }}</button>
        </form>
        <span class="avatar" aria-hidden="true">{{ $editor['initial'] ?? '' }}</span>
        <span class="sr-only" data-slot="editor">{{ $editor === null ? '' : $t('bar.editor', ['name' => $editor['first_name'] ?? $editor['initial']]) }}</span>
    </div>
</div>
