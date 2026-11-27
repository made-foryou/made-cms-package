{{--
    How a title or a description measures up to what Google shows, as a bar and in words for whoever does not see the
    colour. The fill is an attribute of the svg, never a style, so a strict content security policy keeps it. In the
    panel $name and $id let cms.js follow the typing and let the field point at the words.
--}}
@php
    $name ??= null;
    $id ??= null;
@endphp
<span class="metabalk {{ $bar['tone'] }}"@if ($name !== null) data-meta-bar="{{ $name }}"@endif><svg viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="{{ $bar['fill'] }}" height="6"/></svg><span class="sr-only"@if ($id !== null) id="{{ $id }}" data-meta-said="{{ $name }}"@endif>{{ $bar['said'] }}</span></span>
