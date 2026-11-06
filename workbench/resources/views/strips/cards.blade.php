{{--
    The cards strip of the workbench: rich values come ready to render, a link is a url or null.
    Every card is one direct child of data-cms-items; the fields in it name the field of the item.
--}}
<section data-cms-strip="{{ $strip->id }}" data-kolommen="{{ $strip->kolommen }}">
    @if ($strip->kop)
        <h2 data-cms-field="kop">{{ $strip->kop }}</h2>
    @endif

    <ul data-cms-items="kaarten">
        @foreach ($strip->kaarten as $kaart)
            <li>
                <h3 data-cms-field="titel">{{ $kaart['titel'] }}</h3>

                @if ($kaart['tekst'])
                    <p data-cms-field="tekst">{!! nl2br(e($kaart['tekst'])) !!}</p>
                @endif

                @if ($kaart['link'])
                    <a href="{{ $kaart['link'] }}" data-cms-field="link">Lees meer</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
