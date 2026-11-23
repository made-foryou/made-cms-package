{{-- The card "Wat erop staat" (design 15.2): the strips of the working copy, each with the way into the edit shell. --}}
<section class="kaart" aria-labelledby="made-cms-inhoud">
    <div class="sectiekop">
        <h2 id="made-cms-inhoud">{{ $t('pages.show.strips.heading') }}</h2>
        <a class="tekstlink klein" href="{{ $editUrl }}">{{ $t('pages.show.strips.edit_all') }} <span aria-hidden="true">→</span></a>
    </div>
    @if ($strips === [])
        <p class="kaart-tekst">{{ $t('pages.show.strips.empty') }}</p>
    @else
        <ul class="stroken">
            @foreach ($strips as $strip)
                <li class="strook inhoudrij">
                    <span class="strook-tekst">
                        <span class="strook-naam">{{ $strip['label'] }}</span>
                        <span class="strook-samenvatting">{{ $strip['summary'] }}</span>
                    </span>
                    @if ($strip['hidden'])
                        <span class="status">{{ $t('cms.edit.hidden') }}</span>
                    @endif
                    @if ($strip['fields'] !== null)
                        <span class="strook-velden">{{ $strip['fields'] }}</span>
                    @endif
                    @if ($strip['editUrl'] !== null)
                        <a class="actie klein" href="{{ $strip['editUrl'] }}" aria-label="{{ $t('pages.show.strips.edit_label', ['strip' => $strip['label']]) }}">{{ $t('pages.show.strips.edit') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="strook-uitleg">{{ $t('pages.show.strips.hint') }}</p>
    @endif
</section>
