{{--
    The cards strip of the workbench: rich values come ready to render, a link is a url or null.
    Every card is one direct child of data-cms-items; the fields in it name the field of the item.
    A heading renders nothing without a text, and its look hangs on a class, as the editor may change its level.
--}}
<section data-cms-strip="{{ $strip->id }}" data-kolommen="{{ $strip->kolommen }}">
    <x-made-cms::heading :heading="$strip->kop" field="kop" class="kaarten__kop" />

    <ul data-cms-items="kaarten">
        @foreach ($strip->kaarten as $kaart)
            <li>
                <x-made-cms::heading :heading="$kaart['titel']" field="titel" class="kaart__titel" />

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
