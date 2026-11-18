---
name: made-cms-package-site-conversion
description: >
  Turn a Claude Design export, a design prototype or an existing website into a Made CMS site: choose a small set of
  reusable strip types, pick the right field types, write the strip classes and views, and seed the pages through
  made-cms-package. Use this whenever someone wants to convert, port, couple or "koppelen aan het CMS" a design or
  website, wants strip types or fields derived from a design, asks how many strips or which fields a section needs,
  or wants a Claude Design project, mockup, HTML prototype or live site to become editable in Made CMS, even when
  they never say "strip".
license: MIT
metadata:
  author: Made
---

# Convert a design or site to Made CMS

Use this skill when a design (a Claude Design export first, otherwise a prototype, mockup or an existing site) has to
become a Laravel site whose pages editors build from strips in `made-foryou/made-cms-package`. Install and general
adoption are in `made-cms-package-development`; this skill is about the decisions in between: which strips, which
fields, which markup.

## The principle

Editors add, move and remove whole sections, so the catalogue of strip types is a product, not a copy of the design.
A design with 14 sections usually needs 6 to 10 types. Every extra type is one more thing an editor must choose
between, one more class and view to keep, and a pattern that is hard to find again. So the job is to **find the
patterns in the design**, not to mirror its sections one to one.

Two questions decide almost everything:

1. *Would an editor add, move or remove this on their own?* Yes: a strip. No: it belongs to the layout (header,
   navigation, footer, cookie banner) or stays in the view.
2. *Does this differ per use, and would an editor change it without a developer?* Yes: a field. No: it stays in the
   view, the css or the translations.

## Workflow

Work in this order. Do not write classes before the inventory and the catalogue are agreed with the user, because a
`key()` is stored with every strip and cannot change once content uses it.

### 1. Look at the host first

- Which stack renders pages: Blade, Inertia with React or Vue, Livewire? Views follow it (`references/markup-and-views.md`).
- Which strips already exist in `config('made-cms-package.strips.path')` (default `app/Cms/Strips`)? Reuse and extend
  before creating. Never overwrite an existing class or view.
- Is `Route::madeCms(...)` the last line of `routes/web.php`, are the migrations run, does an editor exist? If not,
  follow `made-cms-package-development` first.
- Read the project's own `CLAUDE.md` and match its code style, language and naming.

### 2. Read the design into an inventory

Follow `references/design-export.md`. The result is a table, written down before any code: per page, per section, the
pattern it is, the content elements in it, what repeats, and what is layout. Show it to the user.

### 3. Cluster into a small catalogue

Follow `references/strip-catalogue.md`. Start from the generic types in it and map every section onto one of them
before inventing a new type. Differences in look become a `Choice` variant, differences in amount become an
`Items` list with `min()`/`max()`. A new type needs a reason you can say in one sentence, and the total should stay
at 12 or fewer; above that, merge or ask. Present the catalogue as a table (type, sections that use it, fields,
variants) and wait for the user's go-ahead.

### 4. Choose the fields

Follow `references/field-mapping.md`: which design element becomes which field type, what stays out of fields (colours,
spacing, fixed button texts, form states), and how to label, hint, require and default them.

### 5. Write the strip classes

One `final` class per type in the strips folder, extending `Made\CmsPackage\Content\Strips\StripType`, with a fixed
lowercase `key()`, a `label()` and `description()` that tell an editor what it is for, and `fields()`. The catalogue
checks every definition when it is built, so run `app(\Made\CmsPackage\Content\Strips\StripTypes::class)->all()`
(for example in tinker) after writing; a mistake throws and names the type.

### 6. Write the views

One view or component per type, in the stack of the host, following `references/markup-and-views.md`. The two things
that matter most: `data-cms-strip` on the root of every strip (required, the edit shell finds the strip by it), and
the right element for every `data-cms-field`, so editors see changes while typing.

Move the design's tokens (colours, fonts, spacing) into the site's own css or Tailwind theme once, not into strips.
Keep layout (header, menu from `ListMenuPages`, footer) outside the strips.

### 7. Seed the pages and verify

Follow `references/seeding-and-verify.md`: create the pages and strips through the package's Actions, upload the
design's images into the media library with an alt text, publish, then check the result in the browser in normal mode
and in `?made-cms=edit`, against the design.

### 8. Report

End with a short report: the catalogue (type, where used), what was merged and why, what stayed in the view or the
layout and why, what was invented because the design did not say (copy, alt texts, hrefs), and open questions.

## Hard rules

- Strip definitions only describe; never write to the database or call facades in a strip class.
- Never nest `Items` in `Items`; the package supports one list level. Flatten, or use one value per line (`LongText`).
- Never hand `$page->strips` to a view; render through `RenderStrips` (`<x-made-cms::strips :page="$page" />` in Blade).
- Never create pages with `$page->route()->create()` or store a path on a content table when an Action exists
  (`CreatePage`, `MovePage`); see the seeding reference for the one open exception.
- Never store a url or path for an image in a strip; store the id of a media file (`Image`).
- Never put colours, paddings or font sizes in fields. A handful of named design variants as a `Choice` is fine.
- Never leave out `data-cms-strip`, and never print `Text`, `LongText` or `Heading` values unescaped; only `RichText`
  is cleaned HTML.
- Never change the `key()` of a type that content already uses, and never derive it from the class name.

## Quality checks before you report

- Every section of the design maps to a type from the catalogue, and no two types could be merged by adding a `Choice`.
- No type has more than about 10 fields, no list item more than about 5.
- No field exists that an editor would never touch; no fixed design text lives in a field.
- The first strip of a page prints the page's `h1` once; headings use `Heading` with the level of the design.
- The page looks like the design with the design's own copy in it, and the edit mode outlines every strip.
