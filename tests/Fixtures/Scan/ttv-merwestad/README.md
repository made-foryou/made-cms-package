# TTV Merwestad home

The home page of TTV Merwestad (`made-foryou/ttv-merwestad`, Inertia + React, rendered in the browser, no SSR), coupled to the CMS by hand in #17. It is the acceptance test of the scan: 4.2 recognises strips and fields in `home.html`, 4.4 generates the definitions in `strips/`, and 4.6 imports the content in `content.json`.

| File | What it is |
|---|---|
| `home.html` | The scan's input: the rendered DOM of the live site (`https://ttvmerwestad.nl/`) before the coupling, without any `data-cms-*` attribute. |
| `home.coupled.html` | The answer key: the same page rendered from CMS strips, with `data-cms-strip` on every strip, `data-cms-field` on the fields and `data-cms-items` around the lists. Its Inertia `data-page` holds the rendered strips. |
| `strips/*.php.stub` | The strip types the developer wrote for TTV (`App\Cms\Strips`), as the scan should generate them. `Icons` holds the icon options two of them share. `.stub` keeps them out of Pint. |
| `content.json` | The stored strips of the home page (`type`, `visible`, `values`), as the import should store them. |

The navigation and the footer are no strips. The hero photo and the map are static until the image field (3.2).

## How it was captured

Both pages were captured on 2026-11-03 with headless Chrome through the DevTools protocol: load the page, scroll through it so every reveal and counter has run, wait for the animations, then take `document.documentElement.outerHTML`. A plain `--dump-dom` leaves the reveals hidden and the counters at 0. Scripts, styles, stylesheet links and the launcher were removed afterwards, because they differ between a dev server and a build or come from the package itself.

`tests/Feature/Content/Strips/TtvMerwestadFixtureTest.php` keeps the fixture valid: the stubs are valid strip types, the content passes their validation unchanged, the live page has no `data-cms-*` attribute and the coupled page marks every strip of the content in order.
