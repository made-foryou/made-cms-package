{{-- A page of the CMS as a site renders it: every strip with its view in resources/views/strips. --}}
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

        <x-made-cms::strips :page="$page" />
    </main>
</body>
</html>
