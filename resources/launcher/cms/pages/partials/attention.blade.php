{{-- The card "Hoe hij ervoor staat" (design 15.2, without figures as in 17.16): what the page asks for, the urgent first. --}}
<section class="kaart" aria-labelledby="made-cms-stand">
    <div class="sectiekop">
        <h2 id="made-cms-stand">{{ $t('pages.show.attention.heading') }}</h2>
        <span class="sectienoot">{{ $t('pages.show.attention.caption') }}</span>
    </div>
    @if ($attention['items'] === [])
        <p class="kaart-tekst">{{ $t('pages.show.attention.empty') }}</p>
    @else
        <ul class="aandachtspunten">
            @foreach ($attention['items'] as $item)
                <li class="aandachtspunt">
                    <span class="aandacht-stip {{ $item['severity'] }}" aria-hidden="true"></span>
                    <span class="punt-tekst">
                        <span class="aandacht-titel"><span class="sr-only">{{ $t('pages.show.attention.severity.'.$item['severity']) }}: </span>{{ $item['title'] }}</span>
                        <span class="punt-meta">{{ $item['meta'] }}</span>
                    </span>
                    @if ($item['url'] !== null)
                        <a class="tekstlink klein" href="{{ $item['url'] }}">{{ $item['action'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
