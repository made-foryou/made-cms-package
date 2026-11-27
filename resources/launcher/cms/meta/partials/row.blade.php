{{--
    A page in the list of the Meta-overzicht: its name and address, and the bars of its title and description, or why
    Google does not show it. It opens the page beside the list.
--}}
<li>
    <a @class(['metarij', 'open' => $row['open']]) href="{{ $row['url'] }}"@if ($row['open']) aria-current="true"@endif>
        <span class="metarij-tekst">
            <span class="metarij-naam">{{ $row['name'] }}</span>
            @if ($row['path'] !== null)
                <span class="metarij-pad">{{ $row['path'] }}</span>
            @endif
        </span>
        @if ($row['absent'] !== null)
            <span class="metarij-reden">{{ $row['absent'] }}</span>
        @else
            <span class="metabalken">
                @foreach ($row['bars'] as $bar)
                    @include('made-cms-package-launcher::cms.meta.partials.bar', ['bar' => $bar])
                @endforeach
            </span>
        @endif
    </a>
</li>
