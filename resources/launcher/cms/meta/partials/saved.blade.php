{{-- What the panel just saved, said once where the editor goes on (MetaRedirect). --}}
@if ($saved !== null)
    <div class="melding" role="status">
        <span class="bol goed" aria-hidden="true"></span>
        <span class="melding-tekst">{{ $saved }}</span>
    </div>
@endif
