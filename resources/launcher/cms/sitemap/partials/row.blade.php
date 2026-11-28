{{--
    A page in the list of the sitemap. Its switch is a plain form that cms.js sends once it changes; the screen comes
    back here, and cms.js puts the focus on the switch again (autofocus is left out by a browser on an address with a
    fragment), with "Opgeslagen" beside it. Without a script the form has its own button.
--}}
<li class="sitemaprij" id="{{ $row['anchor'] }}"@if ($row['depth'] > 0) data-diepte="{{ $row['depth'] }}"@endif>
    <span class="sitemaprij-tekst">
        <span class="sitemaprij-naam">{{ $row['name'] }}</span>
        @if ($row['path'] !== null)
            <span class="sitemaprij-pad">{{ $row['path'] }}</span>
        @endif
    </span>
    <span class="sitemaprij-datum"><span class="sr-only">{{ $t('sitemap.screen.list.modified') }} </span>{{ $row['modified'] }}</span>
    <span class="sitemaprij-reden"><span class="bol {{ $row['tone'] }}" aria-hidden="true"></span>{{ $row['reason'] }}</span>
    @if ($row['choice'] !== null)
        <form class="sitemaprij-keuze" method="post" action="{{ $row['choice']['url'] }}" data-autosave>
            @csrf
            @method('PUT')
            <input type="hidden" name="groep" value="{{ $row['choice']['group'] }}">
            <input type="hidden" name="in_sitemap" value="0">
            <label class="sitemaprij-schakel">
                <input class="schakel" type="checkbox" role="switch" name="in_sitemap" value="1" aria-label="{{ $row['choice']['label'] }}"@if ($row['choice']['checked']) checked @endif @if ($row['choice']['saved']) data-refocus @endif>
                <span aria-hidden="true">{{ $t('sitemap.screen.list.switch') }}</span>
            </label>
            @if ($row['choice']['saved'])
                <span class="sitemaprij-opgeslagen" role="status"><span class="bol goed" aria-hidden="true"></span>{{ $t('sitemap.screen.list.saved') }}</span>
            @endif
            <noscript><button type="submit" class="knop stil">{{ $t('sitemap.screen.list.save') }}</button></noscript>
        </form>
    @elseif ($row['elsewhere']['url'] !== null)
        <a class="sitemaprij-elders" href="{{ $row['elsewhere']['url'] }}" aria-label="{{ $row['elsewhere']['label'] }}">{{ $row['elsewhere']['text'] }}</a>
    @else
        <span class="sitemaprij-elders">{{ $row['elsewhere']['text'] }}</span>
    @endif
</li>
