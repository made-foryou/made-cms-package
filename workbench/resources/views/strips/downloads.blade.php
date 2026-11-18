{{--
    The downloads of the workbench: every document is one direct child of data-cms-items, a link to the lasting link of
    the file with its own link text, else the description of the file, else its name, and its type and size.
--}}
<section data-cms-strip="{{ $strip->id }}">
    @if ($strip->kop)
        <h2 data-cms-field="kop">{{ $strip->kop }}</h2>
    @endif
    <ul data-cms-items="bestanden">
        @foreach ($strip->bestanden as $item)
            <li>
                @if ($item['bestand'])
                    <a href="{{ $item['bestand']['url'] }}" data-cms-field="bestand">{{ $item['titel'] ?: ($item['bestand']['description'] ?? $item['bestand']['name']) }}</a>
                    <small>{{ strtoupper($item['bestand']['extension']) }} · {{ \Illuminate\Support\Number::fileSize($item['bestand']['size'], precision: 1) }}</small>
                @endif
            </li>
        @endforeach
    </ul>
</section>
