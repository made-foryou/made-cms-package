{{-- Where the editor is: "Pages / <this page>". --}}
<nav class="kruimel" aria-label="{{ $t('pages.show.trail') }}">
    <a href="{{ $indexUrl }}">{{ \Made\CmsPackage\Access\Enums\Screen::Pages->label() }}</a>
    <span class="kruimel-slash" aria-hidden="true">/</span>
    <span class="kruimel-hier">{{ $here }}</span>
    {{ $slot ?? '' }}
</nav>
