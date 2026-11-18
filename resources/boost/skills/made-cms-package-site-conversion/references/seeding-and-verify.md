# Seeding the pages and verifying the result

Read this when the strip classes and views exist and the site needs its pages, with the design's content in them,
and when you check the result.

## Contents

- Seed through the Actions
- The home page
- Images
- Verify in the catalogue, the browser and the edit mode
- Verification checklist

## Seed through the Actions

Write the content as a seeder, so the conversion can be repeated and reviewed. Go through the package's Actions, not
through the models: they keep the draft, the addresses and the media usage consistent.

```php
$page = CreatePage::run(new NewPage(title: 'Over ons', slug: null, parentId: null));

foreach ($strips as $position => [$type, $values]) {
    $strip = AddStrip::run($page, $type, $position);
    UpdateStrip::run($page, $strip->id, $values, chooseLevels: true);
}

PublishPage::run($page);
```

- `AddStrip` adds a strip of a type at a position and returns it; `UpdateStrip` fills it. Pass `chooseLevels: true` so
  the heading levels of the design are stored, otherwise a heading keeps its default level.
- Values are the stored form: a `Heading` is `{text, tag}` (or a plain text for the default level), a `Link` is
  `{"url": "…"}` or `{"page": id}`, a `Button` is `{text, link}`, an `Image` is the id of a media file, `Items` is a list
  of items. A page link is better than a url for pages of the site, because it survives a changed path.
- Validation runs through `UpdateStrip` and `PublishPage`; a `ValidationException` names the field path. Fix the
  values, do not loosen the field.
- Child pages: `CreatePage::run(new NewPage(title: 'Team', slug: null, parentId: $about->id))`.
- Use the design's own copy so the result can be compared with it, and list in the report what you invented.
- Make the seeder safe to run again: find what it made before and replace or skip it, rather than adding a second copy.
- After writing strips without the Actions (a factory, an import), run `php artisan made-cms:media-usage`.

## The home page

The home page has the address `/` and `CreatePage` builds addresses from a slug, so it cannot make it. An existing
site seeds it by creating the `Page` and its route `/` once and then moving it to the top with `MovePage`, followed by
`AddStrip`, `UpdateStrip` and `PublishPage` as above. This is the one place where the package has no Action yet, so
check the README and `CHANGELOG.md` for a newer one before you write it by hand, and tell the user when you do.

## Images

```php
$media = UploadMedia::run(new UploadedMedia(path: $localFile, filename: 'teamfoto.jpg', folderId: null, uploadedBy: null));
UpdateAltText::run($media, 'Het team in clubshirts in de sporthal');
// 'foto' => $media->id
```

- Upload only content images; design images stay in the site's assets.
- Write a real alt text for every image that carries meaning; an empty text is for purely decorative images.
- Look up an image first (`Media::query()->images()->search($name)->first()`) so a second run keeps one copy.
- The media disk must be usable: `php artisan storage:link` for the `public` disk, or an S3 disk with a `url`.

## Verify in the catalogue, the browser and the edit mode

1. **Catalogue.** Build it: `app(\Made\CmsPackage\Content\Strips\StripTypes::class)->all()`. A broken definition
   throws and names the type.
2. **Pages.** Open each page in the browser. Laravel Herd projects run at `https://<project>.test`; do not start a
   server of your own. Compare each page with the design at a desktop and a phone width.
3. **Edit mode.** Open the page with `?made-cms=edit` as an editor (or "Bewerken" in the edit bar): every strip is
   outlined, and none is missing. The browser console reports a visible strip without `data-cms-strip`.
4. **Watching along.** Change a text, a heading and a button in the side panel and watch the page follow without a
   reload; a field that does not follow has its `data-cms-field` on the wrong element (see
   `markup-and-views.md`).
5. **Headings.** With the Meta-overzicht, the tab "Koppen" lists every heading and flags a missing `h1`, more than one
   `h1`, a skipped level or an empty heading. Fix those in the views.
6. **Images.** Open the media library at `/made-cms/media` and check the alt texts; the images in the page come
   from there, not from files.
7. **The host's own checks.** Run the project's tests, `pint` and static analysis, as its `CLAUDE.md` says.

## Verification checklist

- [ ] Every section of the design is on a page, as a strip of a type from the catalogue.
- [ ] The catalogue has 12 types or fewer, none that a `Choice` or an optional field could merge into another.
- [ ] No field holds a colour, a spacing, a fixed button text, a form state or an alt text.
- [ ] Every strip root has `data-cms-strip`; every editable element has the right `data-cms-field`.
- [ ] Exactly one `h1` per page; headings are `Heading` fields printed with their tag.
- [ ] Layout (header, menu from `ListMenuPages`, footer) sits outside the strips.
- [ ] Content images are in the media library with alt texts; design images are in the site's assets.
- [ ] The seeder can run twice without duplicating pages or images.
- [ ] The report lists the catalogue, merges, what stays in code, what was invented and the open questions.
