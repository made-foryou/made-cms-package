{{--
    The hero strip of the workbench: data-cms-strip on the root is required, data-cms-field per field is optional.
    A field sits on the element that holds only its text; in data-cms-items every direct child is one item.
--}}
<section data-cms-strip="{{ $strip->id }}">
    @if ($strip->label)
        <p><small data-cms-field="label">{{ $strip->label }}</small></p>
    @endif

    <h2 data-cms-field="kop">{{ $strip->kop }}</h2>

    @if ($strip->intro)
        <div data-cms-field="intro">{!! $strip->intro !!}</div>
    @endif

    @if ($strip->knoppen !== [])
        <p data-cms-items="knoppen">
            @foreach ($strip->knoppen as $item)
                @if ($item['knop'])
                    <a href="{{ $item['knop']['url'] }}" data-cms-field="knop">{{ $item['knop']['text'] }}</a>
                @endif
            @endforeach
        </p>
    @endif
</section>
