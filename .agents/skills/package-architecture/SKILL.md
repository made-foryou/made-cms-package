---
name: package-architecture
description: "Use this skill when adding or changing domain code in a bounded context: Actions, Models, custom Eloquent Builders, Data objects, Events, Enums, Exceptions, or when adding a new context such as Sites. Media is part of Content, not a context of its own."
license: MIT
metadata:
  author: made
---

# Package Architecture

## Primary Goal

Place every domain class in the right context and layer, written test-first, so the arch tests in `tests/Arch/` keep passing and models stay a plain representation of their table.

## Workflow

1. Pick the context (`Access` for login and access, `Content` for pages, blocks, publishing and the media library). Ask before creating a new context.
2. Write the failing test first in the mirrored path: `tests/Feature/<Context>/<Layer>/<Class>Test.php` for anything touching the database, `tests/Unit/<Context>/<Layer>/<Class>Test.php` otherwise.
3. Add the smallest class that makes it pass, in `src/<Context>/<Layer>/`.
4. Run `composer test:unit -- --filter=<Class>`, then `composer test:unit -- --testsuite=Arch`, then `composer analyse` and `composer test:types`.
5. Finish with `composer test`.

## Placement

| Kind | Path | Rules |
|---|---|---|
| Action (write, business rule) | `src/<Context>/Actions/` | `final`, `use AsAction`, typed `handle()`, called as `X::run()`. No `asController`/`asListener`/`asJob`/`asCommand`/`rules()`. No `Illuminate\Http`. |
| Model | `src/<Context>/Models/` | Extends `Model`, not final. Only `$table` (`cms_*`), `$timestamps`, `$fillable`, `casts()`, relations, `newEloquentBuilder()`, `newFactory()`. No facades, no Actions or Events. |
| Builder (read) | `src/<Context>/Builders/` | `final`, extends Eloquent `Builder`, `@extends Builder<Model>`, suffix `Builder`, returns `self`. |
| Data | `src/<Context>/Data/` | `final readonly`, extends nothing, promoted constructor properties. |
| Event | `src/<Context>/Events/` | `final`, `use Dispatchable`, readonly constructor properties. |
| Enum | `src/<Context>/Enums/` | String-backed. Labels via `__('made-cms-package::…')`. |
| Strip type (host extension point) | `src/Content/Strips/StripType.php` | Abstract, extended by the site in `app/Cms/Strips`. Host-facing hooks (`key()`, `fields()`, `label()`, `description()`, `view()`, `with()`) stay overridable; derived behaviour (`rules()`, `attributes()`, `defaults()`, `normalize()`, `render()`) is `final`. |
| Field | `src/Content/Strips/Fields/` | `final`, extends `Field`, own `static make(...)`, immutable: every setter clones. Rules keyed by path, `normalize()` for the stored form, `render()` for the form a view gets. No writes, events or facades. |
| Strip type catalogue | `src/Content/Strips/StripTypes.php` | `final`, bound as singleton in the service provider, validates every definition on construction. |
| Scan machinery | `src/Content/Scan/` | `final` classes that read rendered HTML (`Dom\HTMLDocument`) for `RecognisePage` and `CombinePageScans`, and build the source of a proposed strip type (`ScannedStripType`, `StripTypeSource`). Reads and builds, never writes: no Actions, Events, Models, Builders, facades or file writes. The result is `Content\Data\ScanResult` (`docs/scanresultaat.md`). |
| Writing into the site | `src/Content/Actions/GenerateStripTypes.php` | The only code that writes files (`tests/Arch/WritingTest.php`): only at `APP_ENV=local`, with `fopen` mode `x`, never overwriting. A new writer (views of 4.5) is an Action too and joins the arch test. |
| Media library | `src/Content/Models/Media.php`, `MediaFolder.php`, `src/Content/Actions/UploadMedia.php` | Part of `Content`, not a context of its own. A strip stores the id of a file; the alt text belongs to the file. |
| Usage of media | `src/Content/Actions/SyncMediaUsage.php`, `Models/MediaUsage.php` | The only writer of `cms_media_usages`, called by `ChangeDraft` when the strips changed and by `RebuildMediaUsage` (`made-cms:media-usage`); a new Action that writes strips outside `ChangeDraft` runs it in its own transaction. Which values are files comes from `Field::places()`; a new field type that holds files overrides it. Names of places come from the catalogue when shown (`ListMediaPlaces`), never stored. |
| Writing to the media disk | `src/Content/Actions/UploadMedia.php` (through `CheckMediaDisk`) | Only Content Actions use `Storage` (`tests/Arch/WritingTest.php`), never `fopen`. Check the upload first (`CheckUploadedMedia`: type from the contents, size, svg), then write `media/{ulid}/…`, and take the folder off the disk again when the row fails. |
| Exception | `src/<Context>/Exceptions/` | `final`, named after the situation, static constructor such as `for(...)`. |
| Factory | `database/factories/<Context>/` | `final`, namespace `Made\CmsPackage\Database\Factories\<Context>`. |
| Migration | `database/migrations/` | `YYYY_MM_DD_HHMMSS_create_cms_<table>_table.php`, anonymous class. An existing migration is never changed: a change of the schema is a new `…_add_<columns>_to_cms_<table>_table.php` with a later timestamp and a `down()`, which fills in a column a row cannot be without. |

## Examples

Model with its own builder and factory:

```php
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $table = 'cms_pages';

    /** @param  QueryBuilder  $query */
    public function newEloquentBuilder($query): PageBuilder
    {
        return new PageBuilder($query);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
```

Action:

```php
final class PublishPage
{
    use AsAction;

    public function handle(Page $page): Page
    {
        if ($page->status === PageStatus::Published) {
            throw PageAlreadyPublished::for($page);
        }

        $page->status = PageStatus::Published;
        $page->published_at = now()->toImmutable();
        $page->save();

        PagePublished::dispatch($page);

        return $page;
    }
}
```

Adding a context: add its name to `contexts()` in `tests/Pest.php`, then start with one Model and one Action, both tested.

## Anti-Patterns

- Business logic, scopes or facades inside a Model.
- Repositories or query classes next to the Builders.
- A context importing another context. Use an event and a listener instead.
- Empty layer folders created "for later".
- Eloquent class attributes (`#[Table]`, `#[UseEloquentBuilder]`, `#[UseFactory]`); Laravel 12.0 must keep working.
- Changing an arch test to make new code pass.
