{{--
    The hero strip of the workbench: data-cms-strip on the root is required, data-cms-field per field is optional.
    A field sits on the element that holds only its text; in data-cms-items every direct child is one item.
    A heading is printed as the element of its level, so its look hangs on a class, never on h2. The label and the
    heading have emphasis: the words between asterisks are an <em>.
    An image is {url, alt, width, height, focal} or null; the focal point is the place object-position keeps in view.
--}}
<section data-cms-strip="{{ $strip->id }}">
    @if ($strip->label)
        <p><small data-cms-field="label"><x-made-cms::emphasis :text="$strip->label" /></small></p>
    @endif

    <x-made-cms::heading :heading="$strip->kop" field="kop" emphasis class="hero__kop" />

    @if ($strip->intro)
        <div data-cms-field="intro">{!! $strip->intro !!}</div>
    @endif

    @if ($strip->foto)
        <img
            src="{{ $strip->foto['url'] }}"
            alt="{{ $strip->foto['alt'] }}"
            @if ($strip->foto['width']) width="{{ $strip->foto['width'] }}" height="{{ $strip->foto['height'] }}" @endif
            style="max-width: 100%; height: auto; object-fit: cover; object-position: {{ $strip->foto['focal']['x'] * 100 }}% {{ $strip->foto['focal']['y'] * 100 }}%"
            data-cms-field="foto"
        >
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
