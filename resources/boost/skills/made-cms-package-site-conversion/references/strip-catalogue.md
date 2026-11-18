# Strip catalogue: start generic, split only with a reason

Read this when you cluster the sections of a design into strip types. The aim is a catalogue of 6 to 10 types that
covers a whole marketing site, with variants instead of near-duplicates.

## Contents

- How to cluster
- The starter types
- Variants: Choice, Items, or a new type?
- When a new type is justified
- Worked example: before and after

## How to cluster

1. Write each section of the inventory as *what the editor fills in*: "a label, a heading, a paragraph, two buttons
   and a photo", not "the dark blue block at the top".
2. Put sections with (nearly) the same list of contents together. Sections that only differ in layout, background or
   which side the photo is on are one type.
3. Match each group to a starter type below. Adjust that type's fields so the whole group fits; do not fork it.
4. What is left over becomes a new type, but only after the test under "When a new type is justified".

## The starter types

These are suggestions to map onto, not a fixed list. Keep a field only when a section in the design uses it. Type
keys and field names are Dutch (see "Keys and naming" in `field-mapping.md`); the host's own convention wins when it
differs.

| Type (`key`) | Use for | Fields |
|---|---|---|
| `hero` | The opening of a page, with the page's `h1` | `label` (Text), `kop` (Heading, `tag('h1')`, emphasis when the design colours a word), `intro` (RichText or LongText), `knoppen` (Items of Button, `max(2)`), `foto` (Image), `uitlijning` (Choice) when the design shows more than one alignment |
| `tekst` | A heading with running text: about, intro, legal | `label`, `kop`, `tekst` (RichText), `knoppen` (Items of Button, `max(2)`), `breedte` (Choice smal/breed) only if the design has both |
| `tekst-afbeelding` | Text next to a photo or illustration | `label`, `kop`, `tekst` (RichText), `foto` (Image), `knoppen`, `positie` (Choice links/rechts) |
| `kaarten` | A row or grid of 2 to 6 cards: services, features, news teasers, team | `label`, `kop`, `intro`, `kaarten` (Items: `icoon` Choice or `foto` Image, `titel` Heading h3, `tekst` LongText, `link` Link), `kolommen` (Choice) |
| `cijfers` | Stats or key figures | `kop`, `cijfers` (Items: `waarde` Text, `label` Text) |
| `galerij` | A set of photos | `kop`, `fotos` (Items of one Image, `max` as the layout allows) |
| `cta` | A call to action bar | `kop` (Heading), `tekst` (LongText), `knoppen` (Items of Button, `max(2)`) |
| `faq` | Question and answer list | `kop`, `vragen` (Items: `vraag` Text, `antwoord` RichText) |
| `citaten` | Testimonials, quotes | `kop`, `citaten` (Items: `tekst` LongText, `naam` Text, `functie` Text, `foto` Image) |
| `logos` | Partner or client logos | `kop`, `logos` (Items: `logo` Image, `link` Link) |
| `downloads` | Documents to download: forms, schedules, reports | `kop`, `bestanden` (Items: `bestand` File `kinds(MediaKind::Document)`, `titel` Text for the link text) |
| `contact` | Contact details next to a form | `kop`, `intro`, `gegevens` (RichText); the form itself stays in the site code |
| `nieuws` | Latest posts, events, products from the database | `kop`, `aantal` (Choice); the items come from `with()`, not from fields |

Notes:

- A strip type does not have to be shared across sites. If a design needs "opening hours", that is a legitimate
  type when no starter type can hold it; see the test below.
- Forms, maps, embeds, calendars and anything dynamic have no field type. The strip holds the editable texts around
  it (heading, intro), and the view holds the form or embed. Dynamic data (latest news, products) comes from `with()`.

## Variants: Choice, Items, or a new type?

| The design shows | Do this |
|---|---|
| The same content in a different layout (photo left or right, 2 or 3 columns, centred or left text) | A `Choice` field, default set to the most common variant |
| The same block with a different background colour or theme | A `Choice` of named themes only when the design deliberately alternates them; otherwise css decides, from the position, not from a field |
| The same block with more or fewer items | `Items` with `min()` and `max()` |
| The same block, with or without a photo | An optional `Image`; the view hides the part when it is `null` |
| Two blocks that share a heading and intro but hold different items | Two types only if the item fields differ; if they are the same, one type with a `Choice` |
| A block that is really a different thing for an editor (a quote versus a card grid) | A new type |

## When a new type is justified

A new type needs a yes on at least one of these, and you can write the reason in one sentence:

1. **The field set is mostly different.** Fewer than half of the fields overlap with the closest existing type.
2. **The editor thinks of it as something else.** Nobody would look for "a quote" among the cards.
3. **The data behaves differently.** It comes from the database through `with()`, or the item structure is
   different in kind (question and answer, time and activity).

"The design looks different" and "this section has one extra field" are not reasons: add a `Choice` or an optional
field to the existing type.

If you end up above 12 types, stop and review: you are most likely mirroring the design section by section.

## Worked example: before and after

A real site was coupled by hand with eight types for eight sections: `hero`, `cijfers`, `over-ons`, `trainingstijden`,
`teams`, `locatie`, `lid-worden`, `contact`. Looking for patterns:

- `over-ons` (label, heading, intro, three icon cards) and the blocks of `locatie` (three icon blocks) are the same
  pattern: one `kaarten` type, with the heading and intro optional.
- `lid-worden` (heading, text, two buttons) is a `cta`.
- `trainingstijden` (day, time, activities, a colour) and `teams` (name, league, players) are lists of rows; they may
  stay separate when their items differ in kind, but the colour of an evening belongs in the css, not in a `Choice`.
- `contact` keeps its form in the view; the strip holds heading, intro and details.

That gives seven types instead of eight (`hero`, `cijfers`, `kaarten`, `cta`, `teams`, `trainingstijden`, `contact`),
and the card pattern and the call to action now exist once and can be placed on any page. The count is not the
point: check each type against the questions in `SKILL.md`, and keep the types that are really different things
(`teams`, `trainingstijden`) separate.
