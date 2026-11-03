{{-- The cards strip of the workbench: rich values come ready to render, a link is a url or null. --}}
<section data-cms-strip="{{ $strip->id }}" data-kolommen="{{ $strip->kolommen }}">
    @if ($strip->kop)
        <h2 data-cms-field="kop">{{ $strip->kop }}</h2>
    @endif

    <ul data-cms-items="kaarten">
        @foreach ($strip->kaarten as $kaart)
            <li>
                <h3>{{ $kaart['titel'] }}</h3>

                @if ($kaart['tekst'])
                    <p>{!! nl2br(e($kaart['tekst'])) !!}</p>
                @endif

                @if ($kaart['link'])
                    <a href="{{ $kaart['link'] }}">Lees meer</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
