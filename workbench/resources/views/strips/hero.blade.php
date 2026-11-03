{{-- The hero strip of the workbench: data-cms-strip on the root is required, data-cms-field per field is optional. --}}
<section data-cms-strip="{{ $strip->id }}">
    @if ($strip->label)
        <p data-cms-field="label"><small>{{ $strip->label }}</small></p>
    @endif

    <h2 data-cms-field="kop">{{ $strip->kop }}</h2>

    @if ($strip->intro)
        <div data-cms-field="intro">{!! $strip->intro !!}</div>
    @endif

    @if ($strip->knoppen !== [])
        <p data-cms-items="knoppen">
            @foreach ($strip->knoppen as $item)
                @if ($item['knop'])
                    <a href="{{ $item['knop']['url'] }}">{{ $item['knop']['text'] }}</a>
                @endif
            @endforeach
        </p>
    @endif
</section>
