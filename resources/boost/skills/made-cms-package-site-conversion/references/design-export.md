# Reading a design into an inventory

Read this at the start of a conversion, before you propose any strip type. The goal is a written inventory of the
design that the user can check, and a clear separation of layout, strips, fields and tokens.

## Contents

- Find out what you were given
- Treat the design as a guideline
- Separate layout from strips
- The inventory
- Tokens and assets
- An existing site as the source
- Optional: the scan as a first draft

## Find out what you were given

The shape of a Claude Design export is not fixed and not documented by one source: depending on how it was exported it
is a folder or ZIP with HTML, CSS and JS files, sometimes a README with the intended stack, design tokens, component
structure, per-state screenshots and referenced assets, or a bundle handed over through a "handoff" command. The
design can also be a claude.ai Artifact, which you read with the Artifact tool's `read` action, or a single HTML file.
So do not assume a layout. List the files first and say what you found:

- the pages (one HTML file or route per page) and which are variants of each other,
- shared parts (header, footer, components, partials),
- where the tokens live (css custom properties, a Tailwind config, a theme file) and the fonts,
- the assets (images, icons, logos) and whether they are real or placeholders,
- any README or notes that state a target stack, conventions or what is fixed.

If something you need is missing (only one page of a multi-page site, no mobile layout), ask once, listing what
you have and what you would need, instead of guessing.

## Treat the design as a guideline

A design is a picture of one state, made by a tool that does not know the CMS. Placeholder copy, stock photos and
invented numbers are not content decisions, and a section that exists once in the design may be wanted ten times by
the editors. So:

- derive *patterns*, not pages: ask what an editor would build the next page from,
- mark everything that looks invented (lorem ipsum, sample names, fake figures, stock photos) and say so in the report,
- do not copy a promise the design makes about behaviour (an animation, a form that "sends") unless it is built.

## Separate layout from strips

Not everything on the page is a strip. As a rule:

| Part | Where it belongs |
|---|---|
| Header, navigation, language switch, footer, cookie banner | The layout. The menu comes from `ListMenuPages`. |
| A full-width section an editor could add, move or remove | A strip. |
| A repeating block inside a section | An `Items` list in that strip. |
| Page title (`h1`) | The heading of the first strip, usually `hero`. |
| Dividers, decorative shapes, background patterns | The view and css. |
| A form | Site code; the strip holds only the text around it. |
| Content from the database (latest news, products) | `with()` of a strip, not fields. |

In React or Vue prototypes the component structure is a strong hint: a component used in several places
(`<Card>`, `<Section>`) is a pattern, a component used once is a candidate for merging into a type that already exists.

## The inventory

Write this table out (in your reply or a scratch file) and show it before you propose types. One row per section:

| Page | Section | What the editor fills in | Repeats? | Pattern | Layout or strip |
|---|---|---|---|---|---|
| Home | Opening | label, heading, text, two buttons, photo | no | hero | strip |
| Home | Three reasons | heading, intro, 3 × (icon, title, text) | yes, 3 | cards | strip |
| Home | Footer | links, address | no | | layout |

Then group the rows by pattern (this is the input of `strip-catalogue.md`) and list what repeats across pages. Count
how many times every pattern appears: a pattern that appears once on one page is the best candidate to merge into
another type or to keep as a plain `tekst` section.

## Tokens and assets

- **Tokens** (colours, fonts, spacing scale, radius, shadows): move them once into the site's own css, a Tailwind
  theme or css custom properties. They are not fields. Keep the names the design uses so a later design update maps
  back.
- **Content images** (a hero photo, team photos, gallery): they become media library files through `UploadMedia`
  and are referenced by id in an `Image` field. Write a real alt text for each.
- **Design images** (logo of the site, favicon, patterns, icons that never change): copy into the site's own assets
  and reference them in the layout or view; they are not media library files.
- **Fonts**: load them in the layout; do not add a font field.
- **Icon sets**: if the design uses a fixed set of icons editors pick from, list their names, map them in one place in
  the site (an icon component) and offer them as a `Choice`.

## An existing site as the source

When the source is a running site instead of a design:

1. If the repo is available, read its views or components: the structure is explicit and repeated parts are named.
2. If only the url is available, read the rendered DOM of every page (a browser, not a bare `curl`, when the site
   renders client side) and build the same inventory from it.
3. Keep the site's own markup and styles where they work; the conversion adds `data-cms-strip` and friends and swaps
   hard-coded content for fields. Do not restyle.
4. Be careful with content copied from a site you do not own the content of: ask the user whose it is.

## Optional: the scan as a first draft

The package ships a deterministic scan that reads rendered HTML and proposes strip types and content. Use it for a
first draft only, and only after checking that it is still current (the README and `CHANGELOG.md` say what is
built; the review panel, the view generation and the content import were not built when this skill was written):

- `RecognisePage::run(new ScannedPage($url, $html))` reads one page, `CombinePageScans::run($pages)` turns all
  pages into a `ScanResult` (`->toArray()` is the form described in `docs/scanresultaat.md`), and
  `GenerateStripTypes::run($scan)` writes classes into the strips folder, only at `APP_ENV=local` and never over an
  existing file.
- It splits by markup fingerprint, so it tends to produce one type per section and generic names (`strook-3`). Always
  run its result through the clustering and field rules of this skill; do not hand its types to the user as they are.
- It sees only markup, no computed styles, and it leaves out forms.

A design export is usually static HTML, so the scan can read it; a design that is a React app needs rendering first.
