# Markup and views: what the edit shell needs from every strip

Read this when you write the view or component for a strip type. The package gives a view data that is ready to
render; the view only has to print it in the right elements.

## Contents

- Wiring: the route, the layout, the strips
- The markup contract
- Element per field
- Blade, React and Vue
- Layout shell and what must not block the frame
- Common mistakes

## Wiring: the route, the layout, the strips

- The last line of `routes/web.php` is `Route::madeCms(fn (Page $page) => view('page', ['page' => $page]))` (Blade),
  an `Inertia::render('Page', [... 'strips' => RenderStrips::run($page)->toArray()])` responder (Inertia), or an
  invokable class. Routes of the site go above it.
- Blade: the page view holds `<x-made-cms::strips :page="$page" />`, which renders every strip with
  `resources/views/strips/<key>.blade.php` (or the type's `view()`) and passes `$strip`. A missing view throws
  `MissingStripView`.
- React or Vue: pass `RenderStrips::run($page)->toArray()` as a prop and map `strip.type` to a component of your own.
  Keep one registry object from `key()` to component, and render nothing for an unknown type.
- Rendered values are `{id, type, ...fields}`: a `Link` is a url or `null`, a `Button` is `{text, url}` or `null`, a
  `Heading` is `{text, tag}` or `null`, an `Image` is `{url, alt, width, height, focal}` or `null`, a `File` is
  `{url, name, extension, kind, mime, size, description}` or `null` (the url is the lasting link), `Items` is always a
  list in which every item has an `id` and every field, and `RichText` is cleaned HTML.

## The markup contract

- `data-cms-strip="{id}"` on the root element of **every** strip. Required: the edit shell finds the strip by it.
- `data-cms-field="<key>"` on the element that shows a field, and `data-cms-items="<key>"` on the element around a
  list, are optional and static. With them the editor sees a change while typing and a click on the site opens the
  field. Without them the frame reloads after the save. Add them wherever the element qualifies.
- In `data-cms-items`, every direct child is one item, in order. A `data-cms-field` inside names the field of the item
  (`titel`, not `kaarten.0.titel`) and may sit on the child itself. If the view leaves an item out, the shell reloads
  instead of patching.

## Element per field

The shell changes only the text node, `href`, HTML or image attributes, never the element, so put the attribute on the
element that holds exactly that value.

| Field | The element with `data-cms-field` |
|---|---|
| `Text` | Holds only the text: no icon or other element beside it. With `emphasis()` it holds the text and its `<em>`s, as `<x-made-cms::emphasis>` prints them. |
| `LongText` | Holds only the text. Print line breaks with `nl2br(e(...))` or keep them with `white-space: pre-line`. |
| `RichText` | Prints the HTML unescaped (`{!! !!}`, `dangerouslySetInnerHTML`, `v-html`). |
| `Link` | An element with the address in `href`. |
| `Button` | Holds only the text and has the address in `href`. Show it only when the rendered value is not `null`. |
| `Heading` | The element of its level, holding only the text. In Blade use `<x-made-cms::heading :heading="$strip->kop" field="kop" class="…" />`; in React or Vue use the tag from the value (`const Kop = strip.kop?.tag`, `<component :is="strip.kop.tag">`). |
| `File` | The `<a>` with the url in its `href` and the link text (a `Text` of the item, else `description`, else `name`). Not patched: the frame reloads, which is fine. |
| `Image` | The `<img>` that shows it, without `srcset` and not inside `<picture>`; size an icon with css. A background-image is not patched: the frame reloads, which is fine. Use `focal` as `object-position` with `object-fit: cover`. |
| `Choice`, `Items` | Not patched; the frame reloads. Still put `data-cms-items` on the list. |

Print an image only when it is not `null`, and give the strip a fallback layout so a missing or deleted file never
breaks it.

## Blade, React and Vue

```blade
<section data-cms-strip="{{ $strip->id }}" class="kaarten">
    <x-made-cms::heading :heading="$strip->kop" field="kop" class="kaarten__kop" emphasis />

    <ul data-cms-items="kaarten">
        @foreach ($strip->kaarten as $kaart)
            <li>
                <x-made-cms::heading :heading="$kaart['titel']" field="titel" class="kaart__titel" />
                <p data-cms-field="tekst">{!! nl2br(e($kaart['tekst'])) !!}</p>
                @if ($kaart['link'])
                    <a href="{{ $kaart['link'] }}" data-cms-field="link">Lees meer</a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
```

```tsx
export default function Kaarten({ strip }: { strip: KaartenStrip }) {
  const Kop = strip.kop?.tag;
  return (
    <section data-cms-strip={strip.id}>
      {strip.kop && Kop && <Kop data-cms-field="kop">{strip.kop.text}</Kop>}
      <ul data-cms-items="kaarten">
        {strip.kaarten.map((kaart) => (
          <li key={kaart.id}>
            <p data-cms-field="tekst" className="whitespace-pre-line">{kaart.tekst}</p>
            {kaart.link && <a href={kaart.link} data-cms-field="link">Lees meer</a>}
          </li>
        ))}
      </ul>
    </section>
  );
}
```

Use the `id` of an item as the React or Vue key; the package keeps it the same when the list changes. In React,
type the rendered values once in a `types.ts` (a `Strip`, a `CmsImage`, a `CmsButton`) so every component shares them.

For a field with `emphasis()` in React or Vue, split the text on `/\*([^\s*](?:[^*]*[^\s*])?)\*/` and print the parts at
the odd places as `<em>`.

## Layout shell and what must not block the frame

- Put the header, the menu (from `ListMenuPages::run()`, `->roots` for one level, `$item->path?->value` for the link),
  and the footer in the layout, outside the strips.
- Print the head with `<x-made-cms::meta :page="$page" />` (with Inertia: `DescribePageMeta::run($page)->toArray()`
  in `<Head>`), not a `<title>` of your own: it writes the title and description editors set for Google, the
  canonical address and `noindex`, and names the page in the edit bar. A fixed header can offset itself with the
  `--made-cms-bar-height` css variable.
- The edit mode loads the site in an iframe of the same origin. Do not send `X-Frame-Options: DENY` or
  `frame-ancestors 'none'`, and do not let a full-page cache store `?made-cms=edit` or `?made-cms=preview`.
- Print one `h1` per page, the page's, usually the `hero` heading; do not leave an empty heading element in the layout.
- Print headings as real `h1` to `h6` elements; style them by class.

## Common mistakes

- Putting `data-cms-field` on a wrapper that also holds an icon or other text: the shell would overwrite both.
- Hard-coding `<h2>` for a `Heading` field: the editor can change the level; print the tag from the value.
- Printing a `Text` with `{!! !!}` to get emphasis: use `<x-made-cms::emphasis>` or the `emphasis` attribute.
- Skipping `data-cms-strip` on a strip that renders only in the browser: it is still required; the console reports it.
- Showing a part of the design with hard-coded copy next to editable fields, so the editor changes the field and sees
  no change. Either make it a field or make it clearly fixed.
- Leaving an unused field in the class after a view change.
