{{-- The card "Wat er eerder gebeurde" (design 15.2): every publication is a version, the newest is live, an older one goes back as the draft. --}}
<section class="kaart" id="made-cms-versies" aria-labelledby="made-cms-versies-kop">
    <div class="sectiekop">
        <h2 id="made-cms-versies-kop">{{ $t('pages.show.versions.heading') }}</h2>
        <span class="sectienoot">{{ $t('pages.show.versions.note') }}</span>
    </div>
    @if ($versions['restored'] !== null)
        <div class="melding" role="status">
            <span class="bol goed" aria-hidden="true"></span>
            <span class="melding-tekst">{{ $versions['restored'] }} <a class="tekstlink klein" href="{{ $versions['editUrl'] }}">{{ $t('pages.show.versions.edit') }}</a></span>
        </div>
    @endif
    @if ($versions['empty'] !== null)
        <p class="kaart-tekst">{{ $versions['empty'] }}</p>
    @else
        <ol class="versies">
            @foreach ($versions['items'] as $version)
                <li class="versie">
                    <span class="versie-stip{{ $version['live'] ? ' huidig' : '' }}" aria-hidden="true"></span>
                    <span class="punt-tekst">
                        <span class="versie-titel">{{ $version['title'] }}</span>
                        <span class="punt-meta">{{ $version['meta'] }}</span>
                    </span>
                    @if ($version['restoreUrl'] !== null)
                        @if ($versions['draft'])
                            <details class="bevestig versie-terug">
                                <summary class="knop stil" aria-label="{{ $version['label'] }}">{{ $t('pages.show.versions.restore') }}</summary>
                                <p class="kaart-tekst">{{ $t('pages.show.versions.confirm') }}</p>
                                <form method="post" action="{{ $version['restoreUrl'] }}">
                                    @csrf
                                    <button type="submit" class="knop">{{ $version['confirm'] }}</button>
                                </form>
                            </details>
                        @else
                            <form class="versie-terug" method="post" action="{{ $version['restoreUrl'] }}">
                                @csrf
                                <button type="submit" class="knop stil" aria-label="{{ $version['label'] }}">{{ $t('pages.show.versions.restore') }}</button>
                            </form>
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
