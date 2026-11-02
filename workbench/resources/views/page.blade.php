{{-- A page of the CMS as a site renders it. The strips component arrives with issue #15; until then the strips are listed raw. --}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} · Made CMS Workbench</title>
</head>
<body>
    <main>
        <h1>{{ $page->title }}</h1>

        @foreach ($page->strips as $strip)
            @continue (! $strip['visible'])

            <section data-cms-strip="{{ $strip['id'] }}">
                <p><small>{{ $strip['type'] }}</small></p>

                @isset ($strip['values']['kop'])
                    <h2 data-cms-field="kop">{{ $strip['values']['kop'] }}</h2>
                @endisset
            </section>
        @endforeach
    </main>
</body>
</html>
