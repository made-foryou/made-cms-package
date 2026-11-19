# Field mapping: which design element becomes which field

Read this when you decide the fields of a strip type. Field types live in
`Made\CmsPackage\Content\Strips\Fields`; every field takes `label()`, `hint()`, `required()` and `default()`.

## Contents

- Element to field
- What is not a field
- Headings and emphasis
- Labels, hints, required and defaults
- Keys and naming
- Traps seen in real conversions

## Element to field

| In the design | Field | Why and how |
|---|---|---|
| Small line above a heading ("Over ons", a category) | `Text::make('label')` | One short line, escaped. |
| A heading | `Heading::make('kop')` | Stored as `{text, tag}`. Set `->tag()` to the level the design uses; see below. |
| One sentence or a short blurb, no formatting | `Text` (one line) or `LongText` (several lines, plain) | `LongText` is printed escaped with `nl2br`. |
| Text with bold, a list, two or more paragraphs, or an inline link | `RichText` | Only `p`, `strong`, `em`, `ul`, `ol`, `li` and `a href` survive. Use it for body copy, never for a title. |
| One button or call to action | `Button::make('knop')` | Text and link together; renders as `{text, url}` or `null`. |
| Two or more buttons | `Items::make('knoppen', [Button::make('knop')->required()])->max(2)` | A list of buttons; use `max()` to match the design. |
| A whole card that links somewhere, with a fixed "Lees meer" | `Link::make('link')` | The words "Lees meer" are fixed design text and stay in the view. |
| A photo, illustration or background photo | `Image::make('foto')` | Stores the id of a media file; the alt text belongs to the file. Never give it a `default()`. |
| A download link to a pdf or another document, a video file | `File::make('bestand')->kinds(MediaKind::Document)` plus `Text::make('titel')` for the link text | Stores the id of a media file and renders the lasting link with its name, type, size and description. A list of downloads is `Items` of these two. Never give it a `default()`. |
| An icon from a fixed icon set | `Choice::make('icoon', [...])` | Options map to the site's icon component. Editors pick, they do not upload. |
| An icon or logo editors must be able to upload | `Image` | Only when the set is open ended (partner logos). |
| A repeated block (cards, steps, rows) | `Items::make('kaarten', [...])->min(1)->max(6)` | One level only. `itemLabel('Kaart')` names the add button. |
| Stats | `Items` of `Text` pairs | Keep the sign or unit inside the text ("60+") rather than adding fields. |
| A named set of choices the design really offers (layout left or right, 2 or 3 columns) | `Choice` | Options as `value => label`; set a `default()`. |
| A yes/no switch | `Choice` with two options, or the strip's visibility | There is no boolean field. A strip an editor does not want can simply be hidden. |

## What is not a field

Leaving these out is a feature. Each field is something an editor must understand and a developer must maintain.

- **Colours, paddings, font sizes, border radius.** They are design tokens: put them in the site's css or Tailwind
  theme. If the design alternates two backgrounds, a `Choice` of named themes is acceptable, but first ask whether the
  position in the page decides it.
- **Fixed interface text.** "Lees meer", "Terug naar boven", form labels, button texts that are part of the component
  chrome. They stay in the view or in the site's translations, not in the strip.
- **Form states.** The thank-you heading and text after sending, error messages and field labels belong to the form
  component, not to the strip.
- **Alt texts as fields.** The alt text of an image belongs to the media file (`UpdateAltText`). Do not add a field
  such as `kaart_alt` next to an `Image`.
- **Anchor ids** that only exist so the menu can scroll to a section: derive them in the view or leave them out.
  Add a field only when editors must really choose the anchor.
- **Decorative elements:** dividers, background shapes, gradients, icons that never change. They stay in the view.
- **Dynamic data:** latest news, products, events. Use `with()` to add it; the strip holds only the texts around it.
- **Navigation and footer.** They come from `ListMenuPages` and the layout, not from a strip.

## Headings and emphasis

- Always `Heading`, never a `Text` for something that is a heading in the design. It stores the level with the text.
- Set `->tag('h1')` on the one heading that is the page's `h1`, usually in `hero`. A section heading is the default
  `h2`, a card or item heading `->tag('h3')`. Do not restrict levels with `tags()` unless the design truly forbids
  others; editors who open the Meta-overzicht may choose the level, others see it.
- If the design colours or italicises a word inside a heading ("Meer dan alleen *tafeltennis*"), use
  `Heading::make('kop')->emphasis()` and print it so the `<em>` styles it. Do not add a second field such as
  `kop_accent` for the coloured part: it splits one sentence in two for the editor.
- Style headings by class, never by tag (`h2 {}`), because the level is a value of the field.
- `->emphasis()` is also available on `Text`, for example for a label with one highlighted word.

## Labels, hints, required and defaults

- Labels and hints are what editors read: write them in Dutch, plainly, for a content editor ("Korte regel boven de
  kop", not "eyebrow"). Omit `label()` when the key already reads well as a sentence.
- Use `hint()` for what is not obvious: image size and orientation ("Minimaal 1600 px breed, liggend"), length
  ("Twee zinnen is genoeg"), one value per line.
- `required()` only when the view cannot render without the value. A strip that is half empty and looks wrong is
  worse than a strip with one required field. Remember that `required()` on `Items` means at least one item.
- `default()` only for a `Choice` (the most common variant) or text that really should be there on a new strip.
  Never invent marketing copy as a default.
- `description()` on the type is shown in the catalogue editors pick from: one line saying what the strip is for.

## Keys and naming

- **Field keys are Dutch** (`kop`, `tekst`, `knoppen`, `kaarten`, `foto`, `intro`, `label`). This is a decision of the
  owner and matches the package's own examples, the scan and the sites already coupled. It is an exception to the rule
  that code identifiers are English: a field key is stored content data and the default label an editor sees comes
  from it (`gaat_naar` becomes "Gaat naar"), so it reads as the editor's language. Everything else in the strip class
  (class names, methods, variables, comments) stays English.
- Type keys (`key()`) are lowercase letters, digits and dashes, in Dutch too (`kaarten`, `cijfers`, `over-ons`), except
  for established loanwords (`hero`, `cta`, `faq`, `logos`).
- If the host already has strips with another key style, match the host and say so in the report.
- A field `key` is stored with the content, so choose once and keep it. Do not use `id`, `type` or `visible`, and
  `id` inside an item.
- Name fields after what the content is, not where it sits: `kop`, not `linkerkolom_titel`.
- A field that appears in the item of a list uses the same key as it would elsewhere (`titel`, `tekst`, `link`).

## Traps seen in real conversions

1. **One type per section.** Eight bespoke types for eight sections, each repeating the same `label`/`kop`/`intro`
   and differing only in their items. Look for the pattern the sections share before adding a type.
2. **A heading split in two fields.** `kop` plus `kop_accent` instead of one `Heading` with `emphasis()`.
3. **Style as content.** A `kleur` choice on a list row, a `stijl` choice on every button, backgrounds as fields.
4. **Page chrome in the strip.** Thank-you messages, field labels and "Open in Google Maps" as fields.
5. **Alt text as a strip field** instead of on the media file.
6. **Nested lists.** A team with players as items inside an item: use one `LongText` with a name per line, or flatten.
7. **A dead field.** A field the view does not print, or prints the same text for every strip. Remove it.
8. **A required field for a part of the design that is optional** on some pages, which forces editors to type filler.
