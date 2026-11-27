{{--
    A result in Google (design 15.2, 9.1): the address, the title and the description as Google shows them, light in the
    dark too. cms.js fills in the title and the description while the editor types.
    Expects: $address, $title, $description (null when there is none), $empty (what shows without one), $caption and
    $captionShown (whether the caption is in sight).
--}}
<figure class="google">
    <figcaption @class(['google-bijschrift', 'sr-only' => ! $captionShown])>{{ $caption }}</figcaption>
    <span class="google-adres">{{ $address }}</span>
    <span class="google-titel" data-meta-preview="title">{{ $title }}</span>
    <span class="google-tekst{{ $description === null ? ' leeg' : '' }}" data-meta-preview="description">{{ $description ?? $empty }}</span>
</figure>
