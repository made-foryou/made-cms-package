{{--
    The gallery of the workbench: every photo is one direct child of data-cms-items, a figure with the photo as the
    site renders it (null without one) and its caption. The focal point of a photo places it in its frame.
--}}
<section data-cms-strip="{{ $strip->id }}">
    <ul data-cms-items="fotos" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; list-style: none; padding: 0">
        @foreach ($strip->fotos as $foto)
            <li>
                <figure style="margin: 0">
                    @if ($foto['foto'])
                        <img src="{{ $foto['foto']['url'] }}" alt="{{ $foto['foto']['alt'] }}" width="{{ $foto['foto']['width'] }}" height="{{ $foto['foto']['height'] }}" data-cms-field="foto" style="width: 100%; height: 180px; object-fit: cover; object-position: {{ $foto['foto']['focal']['x'] * 100 }}% {{ $foto['foto']['focal']['y'] * 100 }}%">
                    @endif

                    @if ($foto['bijschrift'])
                        <figcaption data-cms-field="bijschrift">{{ $foto['bijschrift'] }}</figcaption>
                    @endif
                </figure>
            </li>
        @endforeach
    </ul>
</section>
