<header class="kopbalk">
    <div class="kopbalk-links">
        <span class="menuknop-plek">
            <button type="button" class="menuknop" aria-controls="made-cms-menu" aria-expanded="true" aria-label="{{ $t('cms.menu.close') }}" data-label-open="{{ $t('cms.menu.open') }}" data-label-close="{{ $t('cms.menu.close') }}">
                <span class="lijn" aria-hidden="true"></span>
                <span class="lijn" aria-hidden="true"></span>
                <span class="lijn" aria-hidden="true"></span>
            </button>
            <span class="menuhint" aria-hidden="true">{{ $t('cms.menu.hint') }}</span>
        </span>
        @include('made-cms-package-launcher::partials.productlogo', ['label' => $t('cms.product')])
        <span class="site">
            <span class="sitenaam">{{ $site }}</span>
            <span class="live">{{ $t('cms.live') }}</span>
        </span>
    </div>
    <div class="kopbalk-rechts">
        <a class="tekstlink" href="{{ $home }}">{{ $t('cms.view_site') }}</a>
        <span class="wie">
            <span class="avatar" aria-hidden="true">{{ $editor['initial'] ?? '' }}</span>
            <span class="wie-tekst">
                <span class="wie-naam">{{ $editor['first_name'] ?? $editor['initial'] ?? '' }}</span>
                <span class="wie-rol">{{ $roles }}</span>
            </span>
        </span>
    </div>
</header>
