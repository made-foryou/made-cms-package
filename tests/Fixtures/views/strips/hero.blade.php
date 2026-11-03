<section data-cms-strip="{{ $strip->id }}">
    <h2 data-cms-field="kop">{{ $strip->kop }}</h2>
    @if ($strip->tekst)
        <div data-cms-field="tekst">{!! $strip->tekst !!}</div>
    @endif
    @if ($strip->actie)
        <a class="actie" href="{{ $strip->actie['url'] }}">{{ $strip->actie['text'] }}</a>
    @endif
    @foreach ($strip->knoppen as $knop)
        <a class="knop" href="{{ $knop['gaat_naar'] }}">{{ $knop['tekst'] }}</a>
    @endforeach
</section>
