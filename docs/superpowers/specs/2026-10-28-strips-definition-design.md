# Defining strips and passing their context to the CMS

Date: 2026-10-28 · Status: design approved, implementation not started

## Context

Existing websites have to become editable through the CMS. The design canvas (artboards 1.7 and 2.7, "Bewerkmodus") shows the goal: after logging in, the site gets the edit bar and every content strip ("strook") gets an amber outline with a tab such as "Hero · bewerken". Clicking a strip selects it, and later a side panel opens to change its texts and images.

This spec covers the first step only: **how a developer quickly defines the strips of an existing site, and how the CMS then knows which strip and which strip type it is looking at.** The side panel for editing content gets its own spec.

The first host is `ttv-merwestad`: Inertia + React, one page, eight strips hardcoded in `resources/js/pages/welcome.tsx`, rendered client-side (no SSR). The design must work just as well for Blade, Vue and Livewire hosts. Everything that crosses the boundary between package and host is therefore JSON or a plain HTML attribute.

## Decisions

1. **A scan sets up a first version; the developer refines it.** The scan is a tool for the developer who connects a site, not a runtime mechanism.
2. **The scan runs in the browser, inside the launcher.** The launcher already sits on every page and sees the rendered DOM, also when React or Vue render client-side. No headless browser, no extra dependencies.
3. **Strip definitions are JSON files in the host repository.** They are edited visually through the site in the local environment, committed and deployed together with the component that renders them, and only read in production. Storing definitions in the database (with migrations or a sync step) was rejected: a structural change (a new or renamed field) always needs a change to the rendering component anyway, so schema and component should travel in the same commit.
4. **The CMS decides the composition of a page (page builder).** A page is an ordered list of strip instances; the host renders one component per strip type.
5. **Five field types:** `text`, `textarea`, `image`, `link`, `list` (one level deep).
6. **Initial content travels as a JSON file** and `php artisan made-cms:pages` creates missing pages on deploy. Existing pages are never touched.
7. **The DOM marker carries only the strip id** (`data-made-cms-strip="12"`). Type and label always come from the server.
8. **Setting up strips is local-only and admin-only** (`content.editable` config, new `Screen::Strips`).

## Developer flow

1. The developer runs the site locally, logs in through the launcher as an admin and clicks "Stroken instellen" in the edit bar.
2. `scan.js` proposes strips and fields; they appear as outlines with a "· voorstel" tab.
3. A side panel per strip shows the proposal: type key and label; per field its name, type, label, an ignore toggle and the value found on the page. Pointing at a field highlights its element on the page.
4. "Opslaan" writes:
   - `resources/cms/strips/<type>.json` for every new or changed strip type;
   - `resources/cms/pages/<page>.json` with the initial content, only when that file does not exist yet;
   - the page and its strips in the local database.
5. The developer connects the host: pass `$page->strips` to the frontend, render one component per type with the `data-made-cms-strip` marker, replace the hardcoded literals with props, and commit definitions and code together.
6. On deploy, `php artisan made-cms:pages` creates the pages that do not exist yet.
7. In production, an editor in edit mode sees the outlines on the real strips; clicking one gives the launcher `{strip, type}`, the hook for the side panel.

## Formats

### Strip definition

`resources/cms/strips/hero.json`. The file name is the key (`kebab-case`), field names are `snake_case`.

```json
{
    "$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json",
    "label": "Hero",
    "fields": [
        { "name": "eyebrow", "type": "text", "label": "Bovenregel" },
        { "name": "title", "type": "text", "label": "Titel" },
        { "name": "intro", "type": "textarea", "label": "Introductie" },
        { "name": "photo", "type": "image", "label": "Foto" },
        { "name": "buttons", "type": "list", "label": "Knoppen", "fields": [
            { "name": "link", "type": "link", "label": "Knop" }
        ] }
    ]
}
```

| Field type | Meaning | Stored content |
|---|---|---|
| `text` | one line | `"TTV Merwestad"` |
| `textarea` | several lines | `"Sinds 1982 …"` |
| `image` | image | `{ "src": "/photos/….jpg", "alt": "…" }` |
| `link` | button or link | `{ "label": "Lid worden", "url": "#lid-worden" }` |
| `list` | repeated group of fields, one level deep | `[ { … }, { … } ]` |

Rules: `list` requires `fields`, other types have none, no `list` inside a `list`. The package ships a JSON Schema (`resources/schema/strip.json`) so editors give autocomplete and errors when a developer edits by hand. Files are written pretty-printed with a fixed key order and a trailing newline, so diffs stay small.

### Initial page content

`resources/cms/pages/home.json`:

```json
{
    "$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/page.json",
    "path": "/",
    "title": "Home",
    "strips": [
        { "type": "hero", "content": { "eyebrow": "Tafeltennisvereniging in Dordrecht", "title": "TTV Merwestad" } },
        { "type": "cijfers", "content": { "items": [ { "value": "60+", "label": "Jaar actief" } ] } }
    ]
}
```

Images stay paths into the host's `public/` folder until there is a media library.

## Components

### Content context

- `Enums/FieldType` (string-backed).
- `Data/Field`, `Data/StripType`, `Data/PageImport`, `Data/StripImport`, `Data/StripSetup`.
- `Exceptions/InvalidStripType`, `Exceptions/InvalidStripContent` (file, strip and field in the message).
- `Actions/ListStripTypes` reads and validates the definitions; `Actions/SaveStripType` writes one, only inside the configured folder and with a validated key.
- `Actions/ValidateStripContent` checks content against a strip type.
- `Actions/ListPageImports` and `Actions/ImportPage` (skips an existing path, otherwise validates everything and creates a published page with its strips in one transaction).
- `Actions/SaveStripSetup` writes the definitions and the initial content of a scanned page and imports it locally.
- `Actions/DescribeStrips` returns `{id, type, label}` for existing strip ids.
- `Models/Strip` (`cms_strips`) with `Builders/StripBuilder`; `Page` gets `strips()` and `PageBuilder::forPath()`.

### Data model

- `cms_pages`: `slug` becomes `path` (unique). `cms_pages` is still unreleased, so the existing create migration changes.
- `cms_strips`: `id`, `page_id` (cascade), `type`, `position`, `content` (json), timestamps; index on (`page_id`, `position`).
- A strip serialises to `{id, type, content}` only, so a host passes `$page->strips` straight to Blade, Inertia or a JSON API.

### Console

`made-cms:pages` runs `ImportPage` for every file in the pages folder, reports created and skipped pages and exits non-zero on invalid files. It is safe to run on every deploy.

### Http

- `GET made-cms/strips?ids[]=…` (`EnsureScreenAccess::using(Screen::Pages)`): `ShowStripsRequest` → `DescribeStrips` → `StripsResponse`. Unknown ids are left out.
- Only when `content.editable` is on, behind `EnsureScreenAccess::using(Screen::Strips)`:
  - `GET made-cms/strips/types`: the existing definitions, for the side panel;
  - `POST made-cms/strips/setup`: `SaveStripSetupRequest` (`toData()` → `StripSetup`) → `SaveStripSetup` → a response listing the written files.
- Whether an editor sees "Stroken instellen" (screen access and `content.editable`) is decided in an Access action; Http passes the outcome to the injected launcher and to the session and login responses.

### Launcher

- `edit.js`: the outline layer, drawn inside the launcher's shadow root so host CSS is never touched. Positions follow the marked elements through `ResizeObserver` and `MutationObserver` (Inertia, Vue, Livewire and Turbo swap the DOM). Motion follows the motion spec: outlines appear staggered 80 ms apart, 240 ms from 0.985× to 1×, hover fills 10% amber, reduced motion keeps only fades. Outlines are buttons; Escape clears the selection. The edit bar shows "Klik op een strook om die te bewerken" or ":strip geselecteerd". Loaded with `import()` when the bar shows.
- `scan.js` (local only):
  - strips: `section` elements and top-level blocks with a heading; `nav`, `header` and `footer` are skipped;
  - key: the element id, else the first heading, else `strook-n`;
  - fields in document order: headings → `text`, `p` → `textarea`, `img` and CSS `background-image` → `image`, `a` and `button` with text → `link`; svg, `aria-hidden`, empty elements and form controls are skipped;
  - lists: two or more siblings with the same tag, classes and field structure become a `list`;
  - an existing strip type is never overwritten by a scan; the panel edits the existing definition instead.
- The side panel (480 px, styled like the login panel) and the outline tab are Blade templates inside the shadow root; all copy lives in `lang/` and the templates, never in JS.
- `edit.js` and `scan.js` are added to the whitelist of `LauncherAssetController`.

### Config

- `content.strips_path` (default `resource_path('cms/strips')`).
- `content.pages_path` (default `resource_path('cms/pages')`).
- `content.editable` (env `MADE_CMS_CONTENT_EDITABLE`, default on only when `APP_ENV=local`).

## Host glue per stack

The package delivers PHP only. The host reads the page and renders one component per type, each putting the strip id on its outer element:

```php
$page = Page::query()->published()->forPath('/')->firstOrFail();
```

```blade
@foreach ($page->strips as $strip)
    @include('strips.'.$strip->type, ['strip' => $strip, 'content' => $strip->content])
@endforeach
{{-- strips/hero.blade.php: <section data-made-cms-strip="{{ $strip->id }}"> … --}}
```

```vue
<component v-for="s in strips" :key="s.id" :is="components[s.type]" :strip="s.id" v-bind="s.content" />
<!-- Hero.vue: <section :data-made-cms-strip="strip"> -->
```

```tsx
{strips.map((s) => { const C = components[s.type]; return <C key={s.id} strip={s.id} {...s.content} />; })}
// Hero.tsx: <section data-made-cms-strip={strip}>
```

## Security

- The setup routes do not exist unless `content.editable` is on, and they require `Screen::Strips`, which only `Admin` holds.
- Files are written only inside the configured folders, with keys validated as `kebab-case`; no path from the request reaches the filesystem.
- The DOM marker is untrusted input: the server resolves type and label from the database and the definitions. The launcher's visibility stays UI only; route middleware is the authorisation.

## Error handling

- An invalid definition or page file throws an exception naming the file, the strip and the field, both when reading and on import; `made-cms:pages` exits non-zero.
- A strip type without a component in the host renders nothing, with a warning only in the local environment (host responsibility, documented).
- When a strips or setup request fails, the bar and the site keep working without outlines.
- The launcher injection keeps reporting failures without breaking the host page.

## Testing

- Pest feature tests for `ListStripTypes`, `SaveStripType` (temporary folder, key order, no traversal), `ValidateStripContent`, `ImportPage` (create, idempotent, validation), `SaveStripSetup`, `DescribeStrips`, the `made-cms:pages` command, the strips and setup routes (setup routes absent when `content.editable` is off, 403 without `Screen::Strips`) and the form requests. The arch tests keep applying.
- `scan.js` and `edit.js` are verified by hand: a Blade fixture page in the workbench, and the React page of `ttv-merwestad` through Herd and Chrome against artboards 1.7 and 2.7 (light and dark). A JS test setup follows when the scan rules grow.

## Out of scope

- The side panel for editing content, with draft, live and publish.
- Adding, removing and reordering strips.
- A media library.
- Migrating content when a field is renamed.
- Generating TypeScript types from the definitions.
- Site-wide blocks (nav, footer) as globals.
- Changing labels in production.
- Rebuilding `welcome.tsx` of `ttv-merwestad`: host work in that repository, on its own branch.
