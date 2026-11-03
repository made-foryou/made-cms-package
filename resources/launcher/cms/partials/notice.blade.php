{{-- What the last change did, on top of the next page; after deleting a page with the way back. --}}
@if ($notice !== null)
    <div class="bericht" role="status">
        <p>{{ $notice['text'] }}</p>
        @if ($notice['restoreUrl'] !== null)
            <form method="post" action="{{ $notice['restoreUrl'] }}">
                @csrf
                <button type="submit" class="actie klein">{{ $t('pages.index.deleted.restore') }}</button>
            </form>
        @endif
    </div>
@endif
