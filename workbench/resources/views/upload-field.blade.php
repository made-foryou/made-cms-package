{{--
    The upload field of the CMS as a host includes it: the stylesheet of the CMS, the partial with what
    UploadField::make() gives, and its script. The library, the picker and the edit shell do the same. With $crop the
    photos of a batch go to the crop step, which the page includes with its script.
--}}
@php
    use Made\CmsPackage\Http\Cms\CropStep;
    use Made\CmsPackage\Http\Cms\UploadField;
    use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

    $upload = UploadField::make($folder, crop: $crop);
    $step = $crop ? CropStep::make(moves: true) : null;
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Uploadveld · Made CMS Workbench</title>
    <style>
        @font-face { font-family: "Made CMS Nunito"; src: url("{{ LauncherAssetController::url('nunito-700.woff2') }}") format("woff2"); font-weight: 700; font-display: swap; }
        @font-face { font-family: "Made CMS Instrument Sans"; src: url("{{ LauncherAssetController::url('instrument-sans.woff2') }}") format("woff2"); font-weight: 400 700; font-display: swap; }
        .werkbank { box-sizing: border-box; max-width: 760px; margin: 0 auto; padding: 46px 40px 70px; display: flex; flex-direction: column; gap: 24px; }
        .werkbank h1 { font: 700 32px/1.15 var(--font-kop); }
        .werkbank .intro { font-size: 16px; line-height: 1.6; color: var(--made-inhoud-zwak); max-width: 62ch; }
        @media (max-width: 520px) { .werkbank { padding: 28px 18px 48px; } }
    </style>
    <link rel="stylesheet" href="{{ LauncherAssetController::url('cms.css') }}">
</head>
<body>
    <main class="werkbank">
        <header>
            <span class="eyebrow">Media / uploadveld</span>
            <h1>Eén veld, vijf momenten</h1>
            <p class="intro">Hetzelfde veld staat straks in de bibliotheek, in de kiezer en in de bewerkmodus. Log eerst in als redacteur; met <code>?folder=</code> komen de bestanden in die map.</p>
        </header>

        @include('made-cms-package-launcher::cms.partials.upload', ['upload' => $upload])
    </main>

    @if ($step !== null)
        @include('made-cms-package-launcher::cms.partials.crop', ['crop' => $step])
        <script type="module" src="{{ $step['script'] }}"></script>
    @endif
    <script type="module" src="{{ $upload['script'] }}"></script>
</body>
</html>
