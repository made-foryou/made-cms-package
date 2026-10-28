# Strips, part 2: the edit mode recognises strips — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** While the edit bar shows, every strip the host marks with `data-made-cms-strip="<id>"` gets the amber outline of artboard 1.7, with the label of its strip type. A click selects the strip and the bar names it. This selection is the hook for the content side panel of a later spec.

**Architecture:**
- The host page only carries strip ids. A new Content action, `DescribeStrips`, turns ids into `{id, type, label}`, reading the type from the database and the label from the strip type file. A JSON route behind `EnsureScreenAccess::using(Screen::Pages)` exposes it.
- In the browser, `launcher.js` loads a new module, `edit.js`, on demand when the bar shows. That module finds the markers, asks the route about unknown ids, and draws outlines in the launcher's shadow root. It follows layout changes and DOM swaps.

**Tech Stack:** PHP 8.3, Laravel 12/13, lorisleiva/laravel-actions, Pest 4/5 with Testbench, Larastan level 7, Pint. Plain ES modules for the launcher (no build step).

**Spec:** `docs/superpowers/specs/2026-10-28-strips-definition-design.md` (sections "Launcher", "Http", "Host glue per stack", "Security").

**Builds on part 1:** `docs/superpowers/plans/2026-10-28-strips-1-definitions-and-pages.md` (PR #10). Already available:
- `Strip` model (`cms_strips`: `id`, `page_id`, `type`, `position`, `content`) and the empty `final class StripBuilder`;
- `ListStripTypes::run(): array<string, StripType>`, which throws `InvalidStripType` on a broken file;
- `StripType` (`key`, `label`, `fields`);
- the test helper `useContentFolders()` in `tests/Pest.php`, with fixture strip types `hero` (label "Hero") and `cijfers` (label "Cijfers").

**Branch:** `feature/strips-edit-mode`, started from `feature/strips`.

## Global Constraints

- PHP `^8.3`, `illuminate/*` `^12.0||^13.0`. **No new Composer or npm dependencies.** The launcher stays plain ES modules served by `LauncherAssetController`, with no build step.
- **Context rules** (enforced by `tests/Arch/*`):
  - Actions are `final`, `use AsAction` and have a typed `handle()`.
  - Data objects are `final readonly` plain classes.
  - Content never uses Access, Http or Console.
  - Http never uses `Model`, `Builder` or `DB`.
  - Controllers are `final` with the suffix `Controller` and run one action.
  - Form requests are `final`, extend `LauncherRequest` and turn input into context Data with `toData()`.
  - JSON answers come from a class in `src/Http/Responses/`.
- **Copy:** all copy lives in `lang/nl` and `lang/en` and in the launcher's Blade templates, never in JS or JSON. JSON carries data only.
- **The DOM marker** is `data-made-cms-strip="<id>"` with a positive integer strip id, and nothing else. Type and label always come from the server; the DOM is never trusted for more.
- **Route:** `GET made-cms/strips`, named `made-cms-package.strips`.
  - It sits in the route group with `SetLauncherLocale` and `SetCacheHeaders:no_store;private`, with middleware `EnsureScreenAccess::using(Screen::Pages)`.
  - Query `ids[]`: 1 to 100 positive integers.
  - Answer: `{"strips": [{"id": 12, "type": "hero", "label": "Hero"}]}`, ordered by id, leaving out unknown ids.
- **Labels:** a strip type without a definition keeps its key as label. A broken definition file is `report()`ed and every strip keeps its key, so one broken file never hides every outline.
- **Visibility and authorisation:** the launcher's visibility is UI only; the route middleware is the authorisation.
- **Outline look** (artboards 1.7/2.7; the same in light and dark):
  - a `2px solid` amber (`--made-amber`, `#be8a1c`) border with a `12px` radius;
  - hover and selected both fill `rgba(190, 138, 28, .10)`, transitioned over `120ms ease`;
  - the tab sits top-left (`top: -2px; left: -2px`), with an amber background, nachtgroen text (`--made-nachtgroen`), Nunito 700 `12px`, padding `5px 10px` and radius `12px 0 10px 0`;
  - the tab text is `<label> · bewerken` (English: `<label> · edit`).
- **Outline motion** (motion spec 1.9):
  - each outline comes in over `240ms var(--ease-uit)`, from `opacity: 0; transform: scale(.985)`;
  - the first outline starts after `120ms`, each next one `80ms` later;
  - only the outlines that come with the bar stagger in; strips that appear later show at once;
  - under `prefers-reduced-motion` they only fade in, over `120ms`.
- **Outline placement:** outlines live in the launcher's shadow root, in a layer with `z-index: 2147482999`, just under the bar (`2147483000`).
- **Selection:**
  - a click on an outline selects its strip; a click on the selected one, or Escape, clears it;
  - the bar shows `· Klik op een strook om die te bewerken`, or `· <label> geselecteerd` when a strip is selected;
  - the bar shows neither on a page without outlined strips.
- **Loading `edit.js`:** it is loaded only when the edit bar shows, by a dynamic `import()` of its versioned URL (`data-edit-script` on `<made-cms-launcher>`), and it is whitelisted in `LauncherAssetController`.
- **Changelog:** every change a host or editor notices gets a line at the **top** of `### Added` under `## [Unreleased]` in `CHANGELOG.md`, in the same commit.
- **Before every commit:** run `composer lint`, then `composer test` (PHPStan, pint check, 100% type coverage of `src`, the full Pest suite with arch tests). All must be green.

## File Structure

| File | Responsibility |
|---|---|
| `src/Content/Data/StripLookup.php` | The strip ids the edit mode asks about |
| `src/Content/Data/StripDescription.php` | One strip as the edit mode outlines it: id, type, label |
| `src/Content/Builders/StripBuilder.php` (modify) | `forIds()` |
| `src/Content/Actions/DescribeStrips.php` | ids → descriptions, labels from the strip type files, tolerant of broken files |
| `src/Http/Requests/Content/ShowStripsRequest.php` | Validates `ids[]`, `toData()` → `StripLookup` |
| `src/Http/Controllers/Content/StripsController.php` | Runs `DescribeStrips` |
| `src/Http/Responses/StripsResponse.php` | `{strips: [...]}` |
| `routes/made-cms-package.php`, `src/Http/Launcher/Endpoints.php` (modify) | The route and its URL for the launcher |
| `resources/launcher/edit.js` | The edit mode: markers, outlines, placement, selection |
| `resources/launcher/launcher.js` (modify) | `Api.strips()`, start and stop of the edit mode, the bar text |
| `resources/launcher/partials/outlines.blade.php` | The outline layer and the outline template |
| `resources/launcher/partials/edit-bar.blade.php`, `launcher.blade.php` (modify) | The bar text slot, the script URL, the outlines include |
| `resources/launcher/launcher.css` (modify) | Outline and bar text styles |
| `lang/{nl,en}/launcher.php` (modify) | `edit.*` copy |
| `src/Http/Middleware/InjectLauncher.php`, `src/Http/Controllers/Access/LauncherAssetController.php` (modify) | `editScript` URL, the `edit.js` whitelist entry |
| `workbench/routes/web.php`, `workbench/database/seeders/DatabaseSeeder.php` (modify) | A page with marked strips to try the edit mode |

---

### Task 1: Describe the strips the edit mode found

**Files:**
- Create: `src/Content/Data/StripLookup.php`
- Create: `src/Content/Data/StripDescription.php`
- Modify: `src/Content/Builders/StripBuilder.php`
- Create: `src/Content/Actions/DescribeStrips.php`
- Test: `tests/Feature/Content/Actions/DescribeStripsTest.php`
- Test: `tests/Feature/Content/Builders/StripBuilderTest.php` (add one test)
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes (part 1):
  - `Strip` model;
  - `ListStripTypes::run(): array<string, StripType>`, which throws `InvalidStripType`;
  - `StripType->label`;
  - the test helper `useContentFolders(): string`.
- Produces:
  - `final readonly class StripLookup(list<int> $ids)`;
  - `final readonly class StripDescription(int $id, string $type, string $label)`;
  - `StripBuilder::forIds(list<int> $ids): StripBuilder`, ordered by id;
  - `DescribeStrips::run(StripLookup $lookup): list<StripDescription>`, ordered by id. It leaves out unknown ids, uses the key as the label for a type without a valid definition, and reports an `InvalidStripType`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Content/Builders/StripBuilderTest.php`:

```php
it('finds the strips with the given ids, in the order of their ids', function (): void {
    $first = Strip::factory()->create();
    Strip::factory()->create();
    $third = Strip::factory()->create();

    expect(Strip::query()->forIds([$third->id, $first->id, 999])->pluck('id')->all())->toBe([$first->id, $third->id]);
});
```

`tests/Feature/Content/Actions/DescribeStripsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\DescribeStrips;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('describes the strips it is asked about with the label of their type', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);
    $figures = Strip::factory()->create(['type' => 'cijfers']);
    Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$figures->id, $hero->id])))->toEqual([
        new StripDescription(id: $hero->id, type: 'hero', label: 'Hero'),
        new StripDescription(id: $figures->id, type: 'cijfers', label: 'Cijfers'),
    ]);
});

it('leaves out ids without a strip', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$hero->id, 999])))->toHaveCount(1);
});

it('labels a strip whose type has no definition with its key', function (): void {
    $strip = Strip::factory()->create(['type' => 'teams']);

    expect(DescribeStrips::run(new StripLookup(ids: [$strip->id]))[0]->label)->toBe('teams');
});

it('reports a broken definition and still describes every strip, labelled with its key', function (): void {
    Exceptions::fake();
    File::put($this->content.'/strips/teams.json', '{"label": ');
    $hero = Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$hero->id]))[0]->label)->toBe('hero');

    Exceptions::assertReported(InvalidStripType::class);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Content/Actions/DescribeStripsTest.php tests/Feature/Content/Builders/StripBuilderTest.php`
Expected: FAIL. `DescribeStripsTest` gives `Class "Made\CmsPackage\Content\Data\StripLookup" not found`, and `StripBuilderTest` gives `Call to undefined method …StripBuilder::forIds()`.

- [ ] **Step 3: Write the implementation**

`src/Content/Data/StripLookup.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips the edit mode found on a page, by id: all a host page tells the CMS about a strip.
 */
final readonly class StripLookup
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(
        public array $ids,
    ) {}
}
```

`src/Content/Data/StripDescription.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip as the edit mode outlines it: its id, its type and the label of that type.
 */
final readonly class StripDescription
{
    public function __construct(
        public int $id,
        public string $type,
        public string $label,
    ) {}
}
```

Replace `src/Content/Builders/StripBuilder.php` with:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\Strip;

/**
 * Reads of strips. A host reads them through their page: $page->strips.
 *
 * @extends Builder<Strip>
 */
final class StripBuilder extends Builder
{
    /**
     * The strips with these ids, in the order of their ids; an id without a strip is left out.
     *
     * @param  list<int>  $ids
     */
    public function forIds(array $ids): self
    {
        return $this->whereKey($ids)->orderBy('id');
    }
}
```

`src/Content/Actions/DescribeStrips.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Strip;

/**
 * Tells the edit mode what the strips it found on a page are. The page only names strip ids; the type always comes
 * from the database. A strip type without a valid definition keeps its key as label, so one broken file never hides
 * every outline.
 */
final class DescribeStrips
{
    use AsAction;

    /**
     * @return list<StripDescription> in the order of their ids
     */
    public function handle(StripLookup $lookup): array
    {
        $labels = $this->labels();

        return array_values(array_map(
            fn (Strip $strip): StripDescription => new StripDescription(
                id: $strip->id,
                type: $strip->type,
                label: $labels[$strip->type] ?? $strip->type,
            ),
            Strip::query()->forIds($lookup->ids)->get()->all(),
        ));
    }

    /**
     * @return array<string, string> the label of every strip type, by key
     */
    private function labels(): array
    {
        try {
            $types = ListStripTypes::run();
        } catch (InvalidStripType $exception) {
            report($exception);

            return [];
        }

        return array_map(fn (StripType $type): string => $type->label, $types);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Content/Actions/DescribeStripsTest.php tests/Feature/Content/Builders/StripBuilderTest.php`
Expected: PASS (4 new tests in `DescribeStripsTest`, plus the 5 tests of `StripBuilderTest`).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added` in `CHANGELOG.md`:

```markdown
- `DescribeStrips` tells the edit mode the type and the label of the strips it found on a page (`StripLookup` and `StripDescription` data objects, `StripBuilder::forIds()`). A strip type without a valid definition keeps its key as label, and a broken definition file is reported instead of hiding every outline.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add src/Content/Data/StripLookup.php src/Content/Data/StripDescription.php src/Content/Builders/StripBuilder.php src/Content/Actions/DescribeStrips.php tests/Feature/Content/Actions/DescribeStripsTest.php tests/Feature/Content/Builders/StripBuilderTest.php CHANGELOG.md
git commit -m "feat: describe the strips the edit mode found on a page"
```

---

### Task 2: The strips endpoint

**Files:**
- Create: `src/Http/Requests/Content/ShowStripsRequest.php`
- Create: `src/Http/Controllers/Content/StripsController.php`
- Create: `src/Http/Responses/StripsResponse.php`
- Modify: `routes/made-cms-package.php`
- Modify: `src/Http/Launcher/Endpoints.php`
- Test: `tests/Feature/Http/Controllers/Content/StripsControllerTest.php`
- Modify: `tests/Feature/Http/Middleware/InjectLauncherTest.php` (the endpoints assertion)
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `DescribeStrips::run(StripLookup): list<StripDescription>`, `StripLookup(list<int> $ids)` and `StripDescription(id, type, label)` (Task 1);
  - `LauncherRequest` (`src/Http/Requests/LauncherRequest.php`);
  - `EnsureScreenAccess::using(Screen $screen): string`;
  - `Screen::Pages`.
- Produces:
  - the route `GET /made-cms/strips` (`made-cms-package.strips`), answering `{"strips": [{"id", "type", "label"}]}`;
  - `Endpoints::all()['strips'] === '/made-cms/strips'`, which the launcher reads as `config.endpoints.strips`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Http/Controllers/Content/StripsControllerTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('tells the edit mode the type and label of the strips on a page', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);
    $figures = Strip::factory()->create(['type' => 'cijfers']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips?'.http_build_query(['ids' => [$figures->id, $hero->id, 999]]))
        ->assertOk()
        ->assertExactJson(['strips' => [
            ['id' => $hero->id, 'type' => 'hero', 'label' => 'Hero'],
            ['id' => $figures->id, 'type' => 'cijfers', 'label' => 'Cijfers'],
        ]]);
});

it('answers every role, because every role opens the pages', function (Role $role): void {
    $strip = Strip::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/strips?ids[]='.$strip->id)
        ->assertOk();
})->with(Role::cases());

it('turns a guest away', function (): void {
    $strip = Strip::factory()->create();

    $this->getJson('made-cms/strips?ids[]='.$strip->id)->assertUnauthorized();
});

it('refuses a request without valid ids', function (string $query): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips'.$query)
        ->assertUnprocessable();
})->with([
    'no ids' => [''],
    'an id that is not a number' => ['?ids[]=hero'],
    'an id below one' => ['?ids[]=0'],
    'more than a hundred ids' => ['?'.http_build_query(['ids' => range(1, 101)])],
]);

it('is never stored by a cache', function (): void {
    $strip = Strip::factory()->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips?ids[]='.$strip->id);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
```

In `tests/Feature/Http/Middleware/InjectLauncherTest.php`, in the test `tells the script where the cms lives, relative to the current host`, add `'strips' => '/made-cms/strips',` to the array passed to `toMatchArray`, directly after `'logout' => '/made-cms/logout',`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/Content/StripsControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php`
Expected: FAIL. `StripsControllerTest` gets 404s (`Expected response status code [200] but received 404`), and the endpoints test fails because the `strips` key is missing.

- [ ] **Step 3: Write the implementation**

`src/Http/Requests/Content/ShowStripsRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The strips the edit mode found on a page: only their ids, read from data-made-cms-strip.
 */
final class ShowStripsRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): StripLookup
    {
        /** @var array<array-key, int|string> $ids */
        $ids = (array) $this->input('ids', []);

        return new StripLookup(ids: array_values(array_unique(array_map(intval(...), $ids))));
    }
}
```

`src/Http/Controllers/Content/StripsController.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DescribeStrips;
use Made\CmsPackage\Http\Requests\Content\ShowStripsRequest;
use Made\CmsPackage\Http\Responses\StripsResponse;

/**
 * The edit mode asks which strips the ids on the page are, to outline them with the label of their type.
 */
final class StripsController
{
    public function __invoke(ShowStripsRequest $request): JsonResponse
    {
        return StripsResponse::make(DescribeStrips::run($request->toData()));
    }
}
```

`src/Http/Responses/StripsResponse.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\StripDescription;

/**
 * The strips of a page as the edit mode outlines them: id, type and the label of the type. Data only.
 */
final class StripsResponse
{
    /**
     * @param  list<StripDescription>  $strips
     */
    public static function make(array $strips): JsonResponse
    {
        return new JsonResponse([
            'strips' => array_map(fn (StripDescription $strip): array => [
                'id' => $strip->id,
                'type' => $strip->type,
                'label' => $strip->label,
            ], $strips),
        ]);
    }
}
```

In `routes/made-cms-package.php`:
- add `use Made\CmsPackage\Http\Controllers\Content\StripsController;` below the `UpcomingScreenController` import;
- inside the group with `SetLauncherLocale::class` and `SetCacheHeaders::class.':no_store;private'`, directly after `Route::post('logout', LogoutController::class)->name('logout');`, add:

```php

            // The edit mode outlines the strips of the page it is on; only their ids come from the page.
            Route::get('strips', StripsController::class)
                ->middleware(EnsureScreenAccess::using(Screen::Pages))
                ->name('strips');
```

In `src/Http/Launcher/Endpoints.php`, add to `ROUTES`, directly after `'start' => 'made-cms-package.start',`:

```php
        'strips' => 'made-cms-package.strips',
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/Content/StripsControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php`
Expected: PASS.

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- Route `GET /made-cms/strips` (`made-cms-package.strips`, for every editor who opens the Pages screen) tells the edit mode the type and label of up to 100 strip ids (`{strips: [{id, type, label}]}`). The launcher gets its url as `endpoints.strips`.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green; the Http arch tests cover the new request, controller and response.

```bash
git add src/Http/Requests/Content/ShowStripsRequest.php src/Http/Controllers/Content/StripsController.php src/Http/Responses/StripsResponse.php routes/made-cms-package.php src/Http/Launcher/Endpoints.php tests/Feature/Http/Controllers/Content/StripsControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php CHANGELOG.md
git commit -m "feat: let the edit mode ask which strips are on the page"
```

---

### Task 3: Outlines in the launcher

**Files:**
- Create: `resources/launcher/edit.js`
- Create: `resources/launcher/partials/outlines.blade.php`
- Modify: `resources/launcher/launcher.js` (`Api`, `showBar()`, `hideBar()`, `disconnectedCallback()`, three new methods)
- Modify: `resources/launcher/launcher.blade.php`
- Modify: `resources/launcher/partials/edit-bar.blade.php`
- Modify: `resources/launcher/launcher.css`
- Modify: `lang/nl/launcher.php`
- Modify: `lang/en/launcher.php`
- Modify: `src/Http/Middleware/InjectLauncher.php` (`render()`)
- Modify: `src/Http/Controllers/Access/LauncherAssetController.php` (`ASSETS`)
- Test: `tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php`
- Test: `tests/Feature/Http/Middleware/InjectLauncherTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `GET {endpoints.strips}?ids[]=…`, which answers `{strips: [{id, type, label}]}` (Task 2);
  - the existing `Api` class and `MadeCmsLauncher` methods in `launcher.js`: `showBar({ animate })`, `hideBar(view)`, `disconnectedCallback()` and `this.bar`;
  - `LauncherAssetController::url(string $asset): string`.
- Produces:
  - `export class EditMode` in `edit.js`:
    - `new EditMode(shadowRoot, { describe: (ids) => Promise<strip[]>, onChange: ({ selected, count }) => void })`;
    - `start({ animate })` and `stop()`;
  - `Api.strips(ids): Promise<Array<{id, type, label}>>`;
  - the launcher methods `startEditing({ animate })`, `stopEditing()` and `showStrip({ selected, count })`;
  - markup:
    - `data-edit-script` on `<made-cms-launcher>`;
    - `.omlijningen` (the layer) and `<template data-outline>`;
    - `[data-strook]`, `[data-strook-hint]`, `[data-strook-gekozen]` and `[data-strook-label]` in the bar;
  - the whitelisted asset `edit.js`.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php`, add a row to the dataset of `serves the launcher assets with their content type`, directly after the `launcher.js` row:

```php
    ['edit.js', 'text/javascript; charset=utf-8'],
```

Append to `tests/Feature/Http/Middleware/InjectLauncherTest.php`:

```php
it('gives the edit mode its script, its outline template and its hint in the bar', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('/')
        ->assertSee('data-edit-script="/made-cms/launcher/edit.js?v=', false)
        ->assertSee('<div class="omlijningen" hidden></div>', false)
        ->assertSee('<template data-outline><button type="button" class="omlijning" aria-pressed="false"><span class="tab"><span data-label></span> · bewerken</span></button></template>', false)
        ->assertSee('<span data-strook-hint>Klik op een strook om die te bewerken</span>', false)
        ->assertSee('<span data-strook-gekozen hidden><span data-strook-label></span> geselecteerd</span>', false);
});

it('words the edit mode in English for an English launcher', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('/')
        ->assertSee('<span data-label></span> · edit</span>', false)
        ->assertSee('<span data-strook-hint>Click a strip to edit it</span>', false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php`
Expected: FAIL. `edit.js` gives a 404, and the injected launcher has no `data-edit-script`.

- [ ] **Step 3: Serve the script and render the markup**

In `src/Http/Controllers/Access/LauncherAssetController.php`, add to `ASSETS`, directly after the `launcher.js` entry:

```php
        'edit.js' => ['edit.js', 'text/javascript; charset=utf-8'],
```

In `src/Http/Middleware/InjectLauncher.php`, `render()`, add directly after `'script' => LauncherAssetController::url('launcher.js'),`:

```php
            'editScript' => LauncherAssetController::url('edit.js'),
```

In `resources/launcher/launcher.blade.php`, line 5, add ` data-edit-script="{{ $editScript }}"` directly after `data-font-text="{{ $fonts['text'] }}"` (before the closing `>` of `<made-cms-launcher …>`). Then directly after `@include('made-cms-package-launcher::partials.edit-bar')` add:

```blade
@include('made-cms-package-launcher::partials.outlines')
```

`resources/launcher/partials/outlines.blade.php`:

```blade
<div class="omlijningen" hidden></div>
<template data-outline><button type="button" class="omlijning" aria-pressed="false"><span class="tab"><span data-label></span> · {{ $t('edit.edit') }}</span></button></template>
```

In `resources/launcher/partials/edit-bar.blade.php`, directly after `<span class="balk-pagina">{{ $page }}</span>`, add:

```blade
        <span class="balk-strook" data-strook hidden aria-live="polite"><span aria-hidden="true">·</span> <span data-strook-hint>{{ $t('edit.hint') }}</span><span data-strook-gekozen hidden><span data-strook-label></span> {{ $t('edit.selected') }}</span></span>
```

In `lang/nl/launcher.php`, add after the `bar` block:

```php
    'edit' => [
        'edit' => 'bewerken',
        'hint' => 'Klik op een strook om die te bewerken',
        'selected' => 'geselecteerd',
    ],
```

In `lang/en/launcher.php`, add after the `bar` block:

```php
    'edit' => [
        'edit' => 'edit',
        'hint' => 'Click a strip to edit it',
        'selected' => 'selected',
    ],
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php`
Expected: FAIL only on the `edit.js` row (the file does not exist yet: `BinaryFileResponse` cannot find it). Every markup assertion passes.

- [ ] **Step 5: Write `edit.js`**

`resources/launcher/edit.js`:

```js
/*
 * Made CMS edit mode (design "Made CMS — Inloggen", 1.7): while the edit bar shows, every strip of the host page gets an
 * outline with the label of its type. The page only marks a strip with its id (data-made-cms-strip); which type it is and
 * how that type is called always comes from the server. The outlines live in the launcher's shadow root, so the host's
 * css never reaches them. Loaded on demand by launcher.js, only for logged-in editors; all copy comes from its templates.
 * Timings follow the motion spec (1.9): outlines come in staggered, 240 ms each, the first 120 ms after the bar.
 */

const MARKER = 'data-made-cms-strip';
const BATCH = 100;
const STAGGER_START = 120;
const STAGGER_STEP = 80;
const SCAN_DELAY = 50;

export class EditMode {
    /**
     * @param {ShadowRoot} root The launcher's shadow root, holding the outline layer and its template.
     * @param {object} options
     * @param {(ids: number[]) => Promise<Array<{id: number, type: string, label: string}>>} options.describe Asks the server about strip ids.
     * @param {(state: {selected: ({id: number, type: string, label: string}|null), count: number}) => void} options.onChange Hears every change of the outlines or the selection.
     */
    constructor(root, { describe, onChange }) {
        this.layer = root.querySelector('.omlijningen');
        this.template = root.querySelector('template[data-outline]');
        this.describe = describe;
        this.onChange = onChange;
        this.known = new Map();
        this.asked = new Set();
        this.outlines = new Map();
        this.selected = null;
        this.running = false;
        this.animate = false;
        this.frame = null;
        this.scanTimer = null;
    }

    /* Starts outlining. With animate, the first outlines come in one after the other while the bar slides in. */
    start({ animate = false } = {}) {
        if (this.running) {
            return;
        }

        this.running = true;
        this.animate = animate;
        this.layer.hidden = false;

        // Inertia, Livewire and Turbo swap parts of the page without a reload: new strips get an outline, gone ones lose it.
        this.mutations = new MutationObserver(() => this.queueScan());
        this.mutations.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [MARKER] });

        // Outlines sit in page coordinates, so scrolling needs nothing; only a change of layout moves them.
        this.resizes = new ResizeObserver(() => this.schedulePlace());
        this.resizes.observe(document.body);
        this.onLayout = () => this.schedulePlace();
        window.addEventListener('resize', this.onLayout);
        window.addEventListener('load', this.onLayout);
        document.fonts?.ready.then(this.onLayout);

        this.onKey = (event) => {
            if (event.key === 'Escape' && this.selected) {
                this.select(null);
            }
        };
        document.addEventListener('keydown', this.onKey);

        this.scan();
    }

    stop() {
        if (!this.running) {
            return;
        }

        this.running = false;
        this.mutations.disconnect();
        this.resizes.disconnect();
        window.removeEventListener('resize', this.onLayout);
        window.removeEventListener('load', this.onLayout);
        document.removeEventListener('keydown', this.onKey);
        clearTimeout(this.scanTimer);

        if (this.frame !== null) {
            cancelAnimationFrame(this.frame);
            this.frame = null;
        }

        this.outlines.forEach(({ button }) => button.remove());
        this.outlines.clear();
        this.selected = null;
        this.layer.hidden = true;
        this.onChange({ selected: null, count: 0 });
    }

    queueScan() {
        clearTimeout(this.scanTimer);
        this.scanTimer = setTimeout(() => this.scan(), SCAN_DELAY);
    }

    /* Finds the marked strips, asks the server once about ids it does not know yet, then draws the outlines. */
    async scan() {
        const unknown = [...this.marked().keys()].filter((id) => !this.known.has(id) && !this.asked.has(id));

        for (let start = 0; start < unknown.length; start += BATCH) {
            const ids = unknown.slice(start, start + BATCH);

            ids.forEach((id) => this.asked.add(id));

            try {
                (await this.describe(ids)).forEach((strip) => this.known.set(strip.id, strip));
            } catch {
                // No answer: these strips get no outline for now and are asked about again after the next change of the page.
                ids.forEach((id) => this.asked.delete(id));
            }
        }

        if (this.running) {
            this.draw(this.marked());
        }
    }

    /* The marked elements of the page by strip id; when an id appears twice, the first element wins. */
    marked() {
        const elements = new Map();

        document.querySelectorAll(`[${MARKER}]`).forEach((element) => {
            const id = Number(element.getAttribute(MARKER));

            if (Number.isInteger(id) && id > 0 && !elements.has(id)) {
                elements.set(id, element);
            }
        });

        return elements;
    }

    /* Adds an outline for every known strip on the page and removes the outlines of strips that left it. */
    draw(elements) {
        let index = 0;

        elements.forEach((element, id) => {
            const strip = this.known.get(id);
            const outline = this.outlines.get(id);

            if (!strip) {
                return;
            }

            if (outline) {
                if (outline.element !== element) {
                    this.resizes.unobserve(outline.element);
                    this.resizes.observe(element);
                    outline.element = element;
                }

                return;
            }

            const button = this.template.content.firstElementChild.cloneNode(true);

            button.querySelector('[data-label]').textContent = strip.label;
            button.addEventListener('click', () => this.select(this.selected?.id === id ? null : strip));

            if (this.animate) {
                button.classList.add('in');
                button.style.animationDelay = `${STAGGER_START + index * STAGGER_STEP}ms`;
                index += 1;
            }

            this.layer.append(button);
            this.resizes.observe(element);
            this.outlines.set(id, { element, button });
        });

        this.outlines.forEach(({ element, button }, id) => {
            if (!elements.has(id)) {
                this.resizes.unobserve(element);
                button.remove();
                this.outlines.delete(id);
            }
        });

        // Only the outlines that come with the bar stagger in; strips that appear later show at once.
        this.animate = false;

        if (this.selected && !this.outlines.has(this.selected.id)) {
            this.selected = null;
        }

        this.place();
        this.onChange({ selected: this.selected, count: this.outlines.size });
    }

    schedulePlace() {
        if (this.frame !== null || !this.running) {
            return;
        }

        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            this.place();
        });
    }

    /*
     * Puts every outline over its strip. Measured against the layer itself, so it works whatever element the layer is
     * positioned in. All reads come before all writes, so the browser lays the page out once.
     */
    place() {
        const origin = this.layer.getBoundingClientRect();
        const boxes = [...this.outlines.values()].map(({ element, button }) => ({ button, rect: element.getBoundingClientRect() }));

        boxes.forEach(({ button, rect }) => {
            button.hidden = rect.width === 0 && rect.height === 0;
            button.style.left = `${rect.left - origin.left}px`;
            button.style.top = `${rect.top - origin.top}px`;
            button.style.width = `${rect.width}px`;
            button.style.height = `${rect.height}px`;
        });
    }

    /* Selects a strip, or nothing: its outline stays lit and the bar names it. The content side panel will open from here. */
    select(strip) {
        this.selected = strip;
        this.outlines.forEach(({ button }, id) => button.setAttribute('aria-pressed', String(strip?.id === id)));
        this.onChange({ selected: strip, count: this.outlines.size });
    }
}
```

- [ ] **Step 6: Connect the launcher to the edit mode**

In `resources/launcher/launcher.js`, class `Api`, add this method directly after `session()`:

```js
    /* The edit mode asks which strips the ids on the page are; the answer is data only: id, type and label. */
    async strips(ids) {
        const query = new URLSearchParams();

        ids.forEach((id) => query.append('ids[]', String(id)));

        const response = await fetch(`${this.endpoints.strips}?${query}`, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            throw new ApiError(response.status);
        }

        return (await response.json()).strips;
    }
```

In `MadeCmsLauncher.showBar({ animate })`, add as the last statement of the method, after the `if (animate) { this.bar.focus(…); }` block:

```js

        this.startEditing({ animate });
```

In `MadeCmsLauncher.hideBar(view)`, add as the first statement of the method:

```js
        this.stopEditing();
```

In `MadeCmsLauncher.disconnectedCallback()`, add directly after `this.flow?.stop();`:

```js
        this.stopEditing();
```

Add these three methods to `MadeCmsLauncher` directly after `showBar()`:

```js
    /* The edit mode (1.7): outlines on the strips of the page while the bar shows. Loaded on first use, never for visitors. */
    async startEditing({ animate }) {
        if (!this.dataset.editScript) {
            return;
        }

        this.editing ??= import(this.dataset.editScript)
            .then(({ EditMode }) => new EditMode(this.shadowRoot, {
                describe: (ids) => this.api.strips(ids),
                onChange: (state) => this.showStrip(state),
            }))
            .catch(() => null);

        const editMode = await this.editing;

        // The bar may have gone again while the script loaded.
        if (editMode && this.dataset.view === 'edit_bar') {
            editMode.start({ animate });
        }
    }

    stopEditing() {
        this.editing?.then((editMode) => editMode?.stop());
    }

    /* The bar names the selected strip, or says how to select one; it says nothing on a page without strips. */
    showStrip({ selected, count }) {
        const holder = this.bar.querySelector('[data-strook]');

        holder.hidden = count === 0;
        holder.querySelector('[data-strook-hint]').hidden = selected !== null;
        holder.querySelector('[data-strook-gekozen]').hidden = selected === null;
        holder.querySelector('[data-strook-label]').textContent = selected?.label ?? '';
    }
```

- [ ] **Step 7: Style the outlines and the bar text**

In `resources/launcher/launcher.css`, directly after the `.avatar { … }` rule, add:

```css

.balk-strook {
    overflow: hidden;
    color: var(--made-mist);
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Edit mode (1.7): an outline on every strip of the page, under the bar. The same in light and dark (2.7). */

.omlijningen {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2147482999;
    width: 0;
    height: 0;
}

.omlijning {
    position: absolute;
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    border: 2px solid var(--made-amber);
    border-radius: 12px;
    background: transparent;
    cursor: pointer;
    transition: background-color 120ms ease;
}

.omlijning:hover,
.omlijning[aria-pressed="true"] {
    background: rgba(190, 138, 28, .10);
}

.omlijning:focus-visible {
    outline: 2px solid var(--made-amber-licht);
    outline-offset: 2px;
}

.omlijning.in {
    animation: omlijning-in 240ms var(--ease-uit) both;
}

.tab {
    position: absolute;
    top: -2px;
    left: -2px;
    padding: 5px 10px;
    border-radius: 12px 0 10px 0;
    background: var(--made-amber);
    color: var(--made-nachtgroen);
    font: 700 12px/1.2 var(--font-kop);
    white-space: nowrap;
}

@keyframes omlijning-in {
    from {
        opacity: 0;
        transform: scale(.985);
    }
}
```

In the last `@media (prefers-reduced-motion: reduce)` block of `launcher.css` (the one that starts with `.stap-in,`), extend the first selector list so that it reads:

```css
    .stap-in,
    .stap-in.terug,
    .balk.in,
    .omlijning.in {
        animation: fade-in 120ms ease-out both;
    }
```

Leave the existing `fade-in` keyframes as they are.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php`
Expected: PASS.

Run: `node --check resources/launcher/edit.js && node --check resources/launcher/launcher.js`
Expected: no output, meaning both files parse. Node 20+ recognises the `export` in `edit.js` as a module.

- [ ] **Step 9: Add the changelog line**

At the top of `### Added`:

```markdown
- Edit mode (design 1.7): while the edit bar shows, every strip the host marks with `data-made-cms-strip="<id>"` gets an amber outline with the label of its strip type ("Hero · bewerken"), staggered in after the bar. Clicking an outline selects the strip and the bar names it ("Hero geselecteerd"); Escape clears the selection. The outlines live in the launcher's shadow root and follow the page through resizes and DOM swaps (Inertia, Livewire, Turbo). Their script (`edit.js`, served by the launcher asset route) only loads for logged-in editors. Dutch and English copy.
```

- [ ] **Step 10: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add resources/launcher/edit.js resources/launcher/partials/outlines.blade.php resources/launcher/launcher.js resources/launcher/launcher.blade.php resources/launcher/partials/edit-bar.blade.php resources/launcher/launcher.css lang/nl/launcher.php lang/en/launcher.php src/Http/Middleware/InjectLauncher.php src/Http/Controllers/Access/LauncherAssetController.php tests/Feature/Http/Controllers/Access/LauncherAssetControllerTest.php tests/Feature/Http/Middleware/InjectLauncherTest.php CHANGELOG.md
git commit -m "feat: outline the strips of the page in the edit mode"
```

---

### Task 4: Docs, conventions and a page to try it on

**Files:**
- Modify: `README.md` (section `## Strips`)
- Modify: `AGENTS.md` (`CLAUDE.md` is a symlink to it; edit AGENTS.md only)
- Modify: `resources/boost/skills/made-cms-package-development/SKILL.md` (step 7)
- Modify: `workbench/routes/web.php`
- Modify: `workbench/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes:
  - everything above (documentation);
  - `Page::query()->forPath()`, `$page->strips`, `Strip::factory()->for($page)` (part 1).
- Produces:
  - documentation of the marker and the edit mode;
  - a workbench page at `/voorbeeld` with three marked strips.

- [ ] **Step 1: Document the marker in the README**

In `README.md`, section `## Strips`, replace everything from the line that starts with `Render one component per strip type` up to and including the closing fence of the Vue example (the code block with `<component v-for="strip in strips" …>`) with:

````markdown
Render one component per strip type, whatever your frontend is, and put the strip id on its outermost element with `data-made-cms-strip`; that attribute is all the edit mode needs (see below). With Blade:

```blade
@foreach ($page->strips as $strip)
    @includeIf('strips.'.$strip->type, ['strip' => $strip, 'content' => $strip->content])
@endforeach
```

```blade
{{-- resources/views/strips/hero.blade.php --}}
<section data-made-cms-strip="{{ $strip->id }}">
    <h1>{{ $content['title'] }}</h1>
</section>
```

With Inertia, pass the strips as a prop (`Inertia::render('page', ['strips' => $page->strips])`) and map the type to a component, in React:

```tsx
const components = { hero: Hero, cijfers: Figures };

{strips.map((strip) => {
    const Strip = components[strip.type];

    return Strip ? <Strip key={strip.id} strip={strip.id} {...strip.content} /> : null;
})}

// Hero.tsx
export function Hero({ strip, title }) {
    return <section data-made-cms-strip={strip}><h1>{title}</h1></section>;
}
```

or in Vue:

```vue
<component v-for="strip in strips" :key="strip.id" :is="components[strip.type]" :strip="strip.id" v-bind="strip.content" />

<!-- Hero.vue -->
<section :data-made-cms-strip="strip"><h1>{{ title }}</h1></section>
```

### Edit mode

While the edit bar shows, every element with `data-made-cms-strip` gets an outline with the name of its strip type ("Hero · bewerken").

- **Selecting:** clicking an outline selects the strip and the bar names it. Escape clears the selection.
- **What the page tells the CMS:** only the strip id. The CMS looks up the type and its label itself (`GET /made-cms/strips`), and only for editors who open the Pages screen.
- **Following the page:** the outlines keep up with resizes and with pages that Inertia, Livewire or Turbo swap without a reload.
- **Loading:** the script only loads for logged-in editors. With a Content Security Policy, allow module scripts from your own site (`'self'`, or `'strict-dynamic'` next to the nonce).
````

- [ ] **Step 2: Add the convention**

In `AGENTS.md`, append this bullet to the end of the `## Architecture Guidelines` list, directly before `## Quick Commands`:

```markdown
- A strip on the host page is marked with `data-made-cms-strip="<id>"` and nothing else. The edit mode (`resources/launcher/edit.js`, loaded on demand when the bar shows) asks `GET made-cms/strips` (`DescribeStrips`) for the type and label and never trusts the DOM for more; its outlines live in the launcher's shadow root.
```

- [ ] **Step 3: Update the Boost skill**

In `resources/boost/skills/made-cms-package-development/SKILL.md`, step `### 7. Strips (page content)`, add this bullet directly after the bullet that starts with `- read a page with`:

```markdown
- put the strip id on the outermost element of every strip component, `data-made-cms-strip="{{ $strip->id }}"` in Blade or `data-made-cms-strip={strip.id}` in JSX and Vue: the edit bar outlines those elements for editors
```

- [ ] **Step 4: Add a workbench page with marked strips**

In `workbench/database/seeders/DatabaseSeeder.php`:
- add `use Made\CmsPackage\Content\Models\Strip;` below the `Page` import;
- replace

```php
        Page::factory()->create([
            'title' => 'Home',
            'path' => '/',
        ]);
```

with

```php
        $home = Page::factory()->create([
            'title' => 'Home',
            'path' => '/',
        ]);

        // Three strips to try the edit mode on at /voorbeeld. Without strip type files their keys are their labels.
        Strip::factory()->for($home)->createMany([
            ['type' => 'hero', 'position' => 0, 'content' => ['title' => 'TTV Merwestad', 'intro' => 'Sinds 1982 dé plek voor tafeltennissers van alle niveaus.']],
            ['type' => 'cijfers', 'position' => 1, 'content' => ['title' => '60+ jaar actief', 'intro' => '35+ leden, 3 competitieteams en twee keer per week training.']],
            ['type' => 'over-ons', 'position' => 2, 'content' => ['title' => 'Meer dan alleen tafeltennis', 'intro' => 'Bij TTV Merwestad draait het om sport, plezier en verbinding.']],
        ]);
```

Replace `workbench/routes/web.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

Route::get('/', function () {
    return view('welcome');
});

// A host page built from the strips of the home page, to try the edit mode: log in through the tile and see the outlines.
Route::get('voorbeeld', function () {
    $page = Page::query()->forPath('/')->firstOrFail();

    $strips = $page->strips->map(fn (Strip $strip): string => sprintf(
        '<section data-made-cms-strip="%d" style="padding: 72px 24px; border-bottom: 1px solid #e3e8f5"><h2 style="margin: 0 0 12px">%s</h2><p style="margin: 0">%s</p></section>',
        $strip->id,
        e((string) ($strip->content['title'] ?? '')),
        e((string) ($strip->content['intro'] ?? '')),
    ))->implode('');

    return response('<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>Voorbeeld</title></head><body style="margin: 0; font-family: system-ui, sans-serif">'.$strips.'</body></html>');
});
```

- [ ] **Step 5: Run the full suite**

Run: `composer lint && composer test`
Expected: all green.

- [ ] **Step 6: Try the edit mode in the workbench**

Run `composer serve` (it builds the workbench: a fresh database with the seeder). Open `http://127.0.0.1:8000/voorbeeld`, then check:
1. The MADE tile shows bottom-right.
2. Log in with `editor@example.com` and the password `password`. After "Je bent binnen" the bar slides in, and three outlines come in one after the other: "hero · bewerken", "cijfers · bewerken" and "over-ons · bewerken". The keys are the labels because the workbench has no strip type files.
3. The bar shows "· Klik op een strook om die te bewerken".
4. Click "cijfers". Its outline stays lightly amber and the bar shows "· cijfers geselecteerd". Escape clears it, and clicking it twice clears it too.
5. Make the window narrow and wide: the outlines follow their strips. Scrolling moves them with the page, under the bar.
6. Reload: the outlines are there at once, without the stagger.
7. Log out: the outlines disappear together with the bar.
8. With the system set to dark mode, the outlines and tabs look the same (artboard 2.7).

If a check fails, fix it and run `composer test` again before committing.

- [ ] **Step 7: Commit**

```bash
git add README.md AGENTS.md resources/boost/skills/made-cms-package-development/SKILL.md workbench/routes/web.php workbench/database/seeders/DatabaseSeeder.php
git commit -m "docs: document the strip marker and add a page to try the edit mode"
```

---

## Manual check in the host (after all tasks)

**In `../ttv-merwestad`**, on its own branch, after the host steps of PR #10:
1. Mark one existing `<section>` with the id of a strip that exists in its database, e.g. `data-made-cms-strip={1}` in `welcome.tsx`.
2. Log in through the tile and check that the outline appears on the React-rendered section.
3. Revert the change afterwards; wiring TTV properly is host work for later.
