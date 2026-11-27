{{-- The card "Bestanden op deze pagina" (design 15.2/16.2): the files on the working copy, an amber dot on a photo without an alt text. --}}
<section class="kaart" id="made-cms-bestanden" aria-labelledby="made-cms-bestanden-kop">
    <div class="sectiekop">
        <h2 id="made-cms-bestanden-kop">{{ $t('pages.show.files.heading') }}</h2>
        @if ($files['searchUrl'] !== null)
            <a class="tekstlink klein" href="{{ $files['searchUrl'] }}">{{ $t('pages.show.files.open') }} <span aria-hidden="true">→</span></a>
        @endif
    </div>
    @if (! $files['available'])
        <p class="kaart-tekst">{{ $t('pages.show.files.unavailable') }}</p>
    @elseif ($files['items'] === [])
        <p class="kaart-tekst">{{ $t('pages.show.files.empty') }}</p>
    @else
        <ul class="paginabestanden">
            @foreach ($files['items'] as $file)
                <li>
                    @if ($file['url'] !== null)
                        <a class="paginabestand" href="{{ $file['url'] }}">@include('made-cms-package-launcher::cms.pages.partials.file', ['file' => $file])</a>
                    @else
                        <span class="paginabestand">@include('made-cms-package-launcher::cms.pages.partials.file', ['file' => $file])</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($files['alt'] !== null)
            <p class="kaart-tekst">{{ $files['alt']['text'] }}@if ($files['alt']['url'] !== null) <a class="tekstlink" href="{{ $files['alt']['url'] }}">{{ $files['alt']['link'] }}</a>@endif</p>
        @endif
    @endif
</section>
