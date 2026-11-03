---
name: package-architecture
description: "Use this skill when adding or changing domain code in a bounded context: Actions, Models, custom Eloquent Builders, Data objects, Events, Enums, Exceptions, or when adding a new context such as Sites or Media."
license: MIT
metadata:
  author: made
---

# Package Architecture

## Primary Goal

Place every domain class in the right context and layer, written test-first, so the arch tests in `tests/Arch/` keep passing and models stay a plain representation of their table.

## Workflow

1. Pick the context (`Access` for login and access, `Content` for pages, blocks and publishing). Ask before creating a new context.
2. Write the failing test first in the mirrored path: `tests/Feature/<Context>/<Layer>/<Class>Test.php` for anything touching the database, `tests/Unit/<Context>/<Layer>/<Class>Test.php` otherwise.
3. Add the smallest class that makes it pass, in `src/<Context>/<Layer>/`.
4. Run `composer test:unit -- --filter=<Class>`, then `composer test:unit -- --testsuite=Arch`, then `composer analyse` and `composer test:types`.
5. Finish with `composer test`.

## Placement

| Kind | Path | Rules |
|---|---|---|
| Action (write, business rule) | `src/<Context>/Actions/` | `final`, `use AsAction`, typed `handle()`, called as `X::run()`. No `asController`/`asListener`/`asJob`/`asCommand`/`rules()`. No `Illuminate\Http`. |
| Model | `src/<Context>/Models/` | Extends `Model`, not final. Only `$table` (`cms_*`), `$fillable`, `casts()`, relations, `newEloquentBuilder()`, `newFactory()`. No facades, no Actions or Events. |
| Builder (read) | `src/<Context>/Builders/` | `final`, extends Eloquent `Builder`, `@extends Builder<Model>`, suffix `Builder`, returns `self`. |
| Data | `src/<Context>/Data/` | `final readonly`, extends nothing, promoted constructor properties. |
| Event | `src/<Context>/Events/` | `final`, `use Dispatchable`, readonly constructor properties. |
| Enum | `src/<Context>/Enums/` | String-backed. Labels via `__('made-cms-package::…')`. |
| Strip type (host extension point) | `src/Content/Strips/StripType.php` | Abstract, extended by the site in `app/Cms/Strips`. Host-facing hooks (`key()`, `fields()`, `label()`, `description()`, `view()`, `with()`) stay overridable; derived behaviour (`rules()`, `attributes()`, `defaults()`, `normalize()`, `render()`) is `final`. |
| Field | `src/Content/Strips/Fields/` | `final`, extends `Field`, own `static make(...)`, immutable: every setter clones. Rules keyed by path, `normalize()` for the stored form, `render()` for the form a view gets. No writes, events or facades. |
| Strip type catalogue | `src/Content/Strips/StripTypes.php` | `final`, bound as singleton in the service provider, validates every definition on construction. |
| Exception | `src/<Context>/Exceptions/` | `final`, named after the situation, static constructor such as `for(...)`. |
| Factory | `database/factories/<Context>/` | `final`, namespace `Made\CmsPackage\Database\Factories\<Context>`. |
| Migration | `database/migrations/` | `YYYY_MM_DD_HHMMSS_create_cms_<table>_table.php`, anonymous class. |

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
