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

    {{--
        A site rendered in the browser (Inertia, React without ssr) has no strips yet when the page has loaded, and then
        scrolls by itself, smoothly, to a place it remembered. The key werkbank.laat in sessionStorage makes this page
        such a site from its next load, for the browser tests of the edit shell and to try it by hand.
    --}}
    <script>
        if (sessionStorage.getItem('werkbank.laat') !== null) {
            history.scrollRestoration = 'manual';
            document.documentElement.toggleAttribute('data-laat', true);
            document.documentElement.style.scrollBehavior = 'smooth';

            addEventListener('load', () => setTimeout(() => {
                document.documentElement.removeAttribute('data-laat');
                setTimeout(() => scrollTo(0, 0), 50);
            }, 100));
        }
    </script>
    <style>
        [data-laat] main { display: none; }
    </style>
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
