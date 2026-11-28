{{--
    What is on it (design 17.3, 17.12): every page in menu order, with when it was last published, whether it is in the
    sitemap and why not, and its switch where that decides it.
--}}
<section class="blok sitemaplijst" aria-labelledby="made-cms-sitemap-lijst">
    <div class="sectiekop">
        <h2 id="made-cms-sitemap-lijst">{{ $t('sitemap.screen.list.heading') }}</h2>
        <span class="sitemaplijst-kolommen" aria-hidden="true">{{ $t('sitemap.screen.list.columns') }}</span>
    </div>
    @if ($rows === [])
        <p class="leeg">{{ $t('sitemap.screen.list.empty') }}</p>
    @else
        <ul class="sitemaprijen" role="list">
            @foreach ($rows as $row)
                @include('made-cms-package-launcher::cms.sitemap.partials.row', ['row' => $row])
            @endforeach
        </ul>
    @endif
    <p class="sitemaplijst-noot">{{ $t('sitemap.screen.list.note') }}</p>
</section>
