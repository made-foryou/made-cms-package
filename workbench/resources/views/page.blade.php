{{--
    A page of the CMS as a site renders it: every strip with its view in resources/views/strips. The main heading of the
    page comes from a strip (the hero), as on most sites; the footer has a fixed heading of the site itself.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} · Made CMS Workbench</title>
</head>
<body>
    <main>
        <x-made-cms::strips :page="$page" />
    </main>

    <footer>
        <h2>Contact</h2>
        <p>Made CMS Workbench</p>
    </footer>
</body>
</html>
