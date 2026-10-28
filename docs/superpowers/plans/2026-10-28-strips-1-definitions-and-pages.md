# Strips, part 1: definitions and pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A host can define its strip types and the initial content of its pages as JSON files, `php artisan made-cms:pages` turns them into pages with ordered strips, and the host reads `$page->strips` to render them.

**Architecture:** Everything lives in the `Content` context. Strip types are JSON files in the host, read and written through Actions into `StripType`/`Field` data objects. Pages get a `path` and an ordered list of `Strip` rows (`cms_strips`). Page files are imported by an Action that never touches an existing page; a console command runs it. Two context-free helpers (`PagePath`, `RelativePath`) go in `Support`.

**Tech Stack:** PHP 8.3, Laravel 12/13 (`illuminate/*`), lorisleiva/laravel-actions, Pest 4/5 with Orchestra Testbench, Larastan level 7, Pint (laravel preset).

**Spec:** `docs/superpowers/specs/2026-10-28-strips-definition-design.md`.

**Roadmap:** This is part 1 of 3.
- **Part 2:** the edit mode recognises strips: `DescribeStrips`, `GET made-cms/strips` and `edit.js` outlines; the `data-made-cms-strip` marker goes into the README there.
- **Part 3:** scanning and setting up strips locally: `content.editable`, `Screen::Strips`, `scan.js`, the side panel, `SaveStripSetup`.

Parts 2 and 3 get their own plans once this part is merged, because they build on its classes.

## Global Constraints

- PHP `^8.3`, `illuminate/*` `^12.0||^13.0`. **No new Composer dependencies.**
- Code identifiers in English. Console copy in Dutch in `lang/nl`, with English in `lang/en`. Exception messages (developer-facing) in English. Tables use the `cms_` prefix.
- **Context rules** (enforced by `tests/Arch/*`):
  - Actions are `final`, `use AsAction`, have a typed `handle()` and are called as `Action::run(...)`.
  - Data objects are `final readonly` plain classes without facades. Exceptions are `final`. Enums are string-backed.
  - Models only describe a row and declare their own `newEloquentBuilder()` and `newFactory()`. There is one `final …Builder` per model.
  - `Content` never uses `Access`, `Http`, `Console` or the service provider. `Support` never uses a context. Console never uses `Model`, `Builder` or `DB` directly.
- No Eloquent class attributes (`#[Table]`, `#[UseEloquentBuilder]`), because Laravel 12.0 must keep working.
- **Naming:**
  - Strip type keys are kebab-case: `^[a-z0-9]+(?:-[a-z0-9]+)*$`.
  - Field names are snake_case: `^[a-z][a-z0-9_]*$`.
  - The field types are exactly `text`, `textarea`, `image`, `link` and `list` (a list is one level deep).
- **Content shapes:**
  - `text`/`textarea` hold a string;
  - `image` holds `{src, alt}` or `null`;
  - `link` holds `{label, url}` or `null`;
  - `list` holds a list of objects.
  - A missing value becomes `''`, `null` or `[]`.
- **Ownership of files and pages:**
  - Strip types are JSON files in the host (`content.strips_path`, default `resource_path('cms/strips')`) and never rows in the database.
  - Page files live in `content.pages_path`, default `resource_path('cms/pages')`.
  - `ImportPage` never changes a page whose path already exists.
- Every change a host notices gets a line at the **top** of `### Added` under `## [Unreleased]` in `CHANGELOG.md`, in the same commit.
- Before every commit run `composer lint` and then `composer test`. The latter runs PHPStan, `lint:check`, 100% type coverage and the full Pest suite including the arch tests, and all of it must be green.

## File Structure

| File | Responsibility |
|---|---|
| `src/Content/Enums/FieldType.php` | The five field types |
| `src/Content/Data/Field.php`, `StripType.php` | A parsed strip type and its fields |
| `src/Content/Exceptions/InvalidStripType.php` | A definition that breaks a rule, with key and field |
| `src/Content/Actions/ParseStripType.php` | Decoded JSON → `StripType`, the single home of the definition rules |
| `src/Content/Actions/ListStripTypes.php` | Every `*.json` in the strips folder → `array<string, StripType>` |
| `src/Content/Actions/SaveStripType.php` | `StripType` → pretty, ordered JSON file (checked by `ParseStripType` first) |
| `src/Support/RelativePath.php` | Relative path from a folder to a file (for `$schema`) |
| `src/Support/PagePath.php` | One spelling per page path (`/lid-worden`) |
| `src/Content/Models/Strip.php`, `Builders/StripBuilder.php` | A row of `cms_strips` |
| `src/Content/Models/Page.php`, `Builders/PageBuilder.php` (modify) | `path`, `strips()`, `forPath()` |
| `src/Content/Exceptions/InvalidStripContent.php` | Content that does not fit its type, or an unknown type |
| `src/Content/Actions/ValidateStripContent.php` | Checks and normalises the content of one strip |
| `src/Content/Data/PageImport.php`, `StripImport.php` | A parsed page file |
| `src/Content/Exceptions/InvalidPageImport.php` | A page file that breaks a rule |
| `src/Content/Actions/ListPageImports.php` | Every `*.json` in the pages folder → `list<PageImport>` |
| `src/Content/Actions/ImportPage.php` | `PageImport` → page with strips, unless the path exists |
| `src/Console/Commands/ImportPagesCommand.php` | `made-cms:pages` |
| `resources/schema/strip.json`, `page.json` | JSON Schemas for editor autocomplete |
| `database/migrations/…000100_create_cms_pages_table.php` (modify), `…000150_create_cms_strips_table.php` | Schema |
| `database/factories/Content/PageFactory.php` (modify), `StripFactory.php` | Factories |
| `tests/Fixtures/content/strips/*.json`, `pages/home.json` | Example host files for the tests |

---

### Task 1: Strip type definitions

**Files:**
- Create: `src/Content/Enums/FieldType.php`
- Create: `src/Content/Data/Field.php`
- Create: `src/Content/Data/StripType.php`
- Create: `src/Content/Exceptions/InvalidStripType.php`
- Create: `src/Content/Actions/ParseStripType.php`
- Create: `resources/schema/strip.json`
- Test: `tests/Unit/Content/Enums/FieldTypeTest.php`
- Test: `tests/Unit/Content/Actions/ParseStripTypeTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `enum FieldType: string { Text='text'; Textarea='textarea'; Image='image'; Link='link'; List='list' }`
  - `final readonly class Field(string $name, FieldType $type, string $label, list<Field> $fields = [])`
  - `final readonly class StripType(string $key, string $label, list<Field> $fields)`
  - `InvalidStripType::because(string $key, string $reason): self`, with the message `Strip type [{key}] is invalid: {reason}.`
  - `ParseStripType::run(string $key, mixed $definition): StripType`, which throws `InvalidStripType`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Content/Enums/FieldTypeTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\FieldType;

it('stores the field type as a string value', function (FieldType $type, string $value): void {
    expect($type->value)->toBe($value);
})->with([
    [FieldType::Text, 'text'],
    [FieldType::Textarea, 'textarea'],
    [FieldType::Image, 'image'],
    [FieldType::Link, 'link'],
    [FieldType::List, 'list'],
]);

it('offers the same field types in the JSON Schema of a strip type', function (): void {
    $schema = json_decode((string) file_get_contents(__DIR__.'/../../../../resources/schema/strip.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['$defs']['field']['properties']['type']['enum'])->toBe(array_column(FieldType::cases(), 'value'));
});
```

`tests/Unit/Content/Actions/ParseStripTypeTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ParseStripType;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

it('reads a strip type with its fields', function (): void {
    $type = ParseStripType::run('hero', [
        '$schema' => '../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json',
        'label' => 'Hero',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Titel'],
            ['name' => 'buttons', 'type' => 'list', 'label' => 'Knoppen', 'fields' => [
                ['name' => 'link', 'type' => 'link', 'label' => 'Knop'],
            ]],
        ],
    ]);

    expect($type)->toEqual(new StripType(key: 'hero', label: 'Hero', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'buttons', type: FieldType::List, label: 'Knoppen', fields: [
            new Field(name: 'link', type: FieldType::Link, label: 'Knop'),
        ]),
    ]));
});

it('accepts a strip type without fields', function (): void {
    expect(ParseStripType::run('contact-form', ['label' => 'Contactformulier', 'fields' => []])->fields)->toBe([]);
});

it('refuses a definition that breaks a rule', function (string $key, mixed $definition, string $message): void {
    expect(fn () => ParseStripType::run($key, $definition))->toThrow(InvalidStripType::class, $message);
})->with([
    'a key that is not kebab-case' => ['Over_Ons', ['label' => 'Over ons', 'fields' => []], 'Strip type [Over_Ons] is invalid: the key is not written in kebab-case'],
    'a path as key' => ['../evil', ['label' => 'Evil', 'fields' => []], 'the key is not written in kebab-case'],
    'not an object' => ['hero', 'Hero', 'Strip type [hero] is invalid: the definition is not an object'],
    'an unknown key' => ['hero', ['label' => 'Hero', 'fields' => [], 'feilds' => []], 'the definition has an unknown key [feilds]'],
    'no label' => ['hero', ['fields' => []], 'the definition needs a label'],
    'no fields' => ['hero', ['label' => 'Hero'], 'the definition needs a list of fields'],
    'a name that is not snake_case' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'Title', 'type' => 'text', 'label' => 'Titel'],
    ]], 'every field needs a name in snake_case, found [Title]'],
    'an unknown field type' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'txt', 'label' => 'Titel'],
    ]], 'field [title] has no known type (text, textarea, image, link, list)'],
    'a field without label' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text'],
    ]], 'field [title] needs a label'],
    'a name used twice' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Titel'],
        ['name' => 'title', 'type' => 'textarea', 'label' => 'Titel'],
    ]], 'field [title] appears twice'],
    'fields on a text' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Titel', 'fields' => []],
    ]], 'field [title] only has fields when it is a list'],
    'a list without fields' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'items', 'type' => 'list', 'label' => 'Items', 'fields' => []],
    ]], 'field [items] needs at least one field'],
    'a list inside a list' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'items', 'type' => 'list', 'label' => 'Items', 'fields' => [
            ['name' => 'sub', 'type' => 'list', 'label' => 'Sub', 'fields' => [
                ['name' => 'text', 'type' => 'text', 'label' => 'Tekst'],
            ]],
        ]],
    ]], 'field [items.sub] is a list inside a list'],
]);
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Content/Enums/FieldTypeTest.php tests/Unit/Content/Actions/ParseStripTypeTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Content\Enums\FieldType" not found`.

- [ ] **Step 3: Write the implementation**

`src/Content/Enums/FieldType.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What an editor fills in for a field of a strip. A new type also needs a branch in ParseStripType and
 * ValidateStripContent and its value in the enum of resources/schema/strip.json.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Image = 'image';
    case Link = 'link';
    case List = 'list';
}
```

`src/Content/Data/Field.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\FieldType;

/**
 * One field of a strip type: what an editor fills in, and for a list the fields of each item.
 */
final readonly class Field
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $label,
        public array $fields = [],
    ) {}
}
```

`src/Content/Data/StripType.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A kind of strip the pages of a site are built from, defined in resources/cms/strips/<key>.json in the host.
 */
final readonly class StripType
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fields,
    ) {}
}
```

`src/Content/Exceptions/InvalidStripType.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidStripType extends RuntimeException
{
    public static function because(string $key, string $reason): self
    {
        return new self("Strip type [{$key}] is invalid: {$reason}.");
    }
}
```

`src/Content/Actions/ParseStripType.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * Reads one strip type from its decoded JSON definition. Strict on purpose: a typo in a hand-edited file
 * is an error that names the field, never a field that silently disappears.
 */
final class ParseStripType
{
    use AsAction;

    private const string KEY = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const string NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * @throws InvalidStripType
     */
    public function handle(string $key, mixed $definition): StripType
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidStripType::because($key, 'the key is not written in kebab-case, e.g. "hero" or "over-ons"');
        }

        if (! is_array($definition)) {
            throw InvalidStripType::because($key, 'the definition is not an object');
        }

        $this->rejectUnknownKeys($key, $definition, ['$schema', 'label', 'fields'], 'the definition');

        return new StripType(
            key: $key,
            label: $this->label($key, $definition, 'the definition'),
            fields: $this->fields($key, $definition['fields'] ?? null, ''),
        );
    }

    /**
     * The fields of the strip type, or of the list at $parent.
     *
     * @return list<Field>
     */
    private function fields(string $key, mixed $fields, string $parent): array
    {
        if (! is_array($fields) || ! array_is_list($fields)) {
            throw InvalidStripType::because($key, $parent === '' ? 'the definition needs a list of fields' : "field [{$parent}] needs a list of fields");
        }

        if ($parent !== '' && $fields === []) {
            throw InvalidStripType::because($key, "field [{$parent}] needs at least one field");
        }

        $parsed = [];

        foreach ($fields as $definition) {
            $field = $this->field($key, $definition, $parent);

            foreach ($parsed as $earlier) {
                if ($earlier->name === $field->name) {
                    throw InvalidStripType::because($key, 'field ['.$this->path($parent, $field->name).'] appears twice');
                }
            }

            $parsed[] = $field;
        }

        return $parsed;
    }

    private function field(string $key, mixed $field, string $parent): Field
    {
        $name = is_array($field) && is_string($field['name'] ?? null) ? $field['name'] : '';
        $path = $this->path($parent, $name);

        if (! is_array($field) || preg_match(self::NAME, $name) !== 1) {
            throw InvalidStripType::because($key, "every field needs a name in snake_case, found [{$path}]");
        }

        $where = "field [{$path}]";

        $this->rejectUnknownKeys($key, $field, ['name', 'type', 'label', 'fields'], $where);

        $type = is_string($field['type'] ?? null) ? FieldType::tryFrom($field['type']) : null;

        if ($type === null) {
            throw InvalidStripType::because($key, "{$where} has no known type (".implode(', ', array_column(FieldType::cases(), 'value')).')');
        }

        if ($type === FieldType::List && $parent !== '') {
            throw InvalidStripType::because($key, "{$where} is a list inside a list");
        }

        if ($type !== FieldType::List && array_key_exists('fields', $field)) {
            throw InvalidStripType::because($key, "{$where} only has fields when it is a list");
        }

        return new Field(
            name: $name,
            type: $type,
            label: $this->label($key, $field, $where),
            fields: $type === FieldType::List ? $this->fields($key, $field['fields'] ?? null, $path) : [],
        );
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function label(string $key, array $definition, string $where): string
    {
        $label = $definition['label'] ?? null;

        if (! is_string($label) || trim($label) === '') {
            throw InvalidStripType::because($key, "{$where} needs a label");
        }

        return trim($label);
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @param  list<string>  $allowed
     */
    private function rejectUnknownKeys(string $key, array $definition, array $allowed, string $where): void
    {
        foreach (array_keys($definition) as $name) {
            if (! in_array($name, $allowed, true)) {
                throw InvalidStripType::because($key, "{$where} has an unknown key [{$name}]");
            }
        }
    }

    private function path(string $parent, string $name): string
    {
        return $parent === '' ? $name : "{$parent}.{$name}";
    }
}
```

`resources/schema/strip.json`:

```json
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "title": "Made CMS strip type",
    "description": "A kind of strip the pages of the site are built from: its label and the fields an editor fills in. The file name is its key, in kebab-case.",
    "type": "object",
    "required": ["label", "fields"],
    "additionalProperties": false,
    "properties": {
        "$schema": { "type": "string" },
        "label": { "type": "string", "minLength": 1, "description": "The name editors see, e.g. \"Hero\"." },
        "fields": { "type": "array", "items": { "$ref": "#/$defs/field" } }
    },
    "$defs": {
        "field": {
            "type": "object",
            "required": ["name", "type", "label"],
            "additionalProperties": false,
            "properties": {
                "name": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$", "description": "The key of the value in the content, in snake_case." },
                "type": { "enum": ["text", "textarea", "image", "link", "list"] },
                "label": { "type": "string", "minLength": 1, "description": "The name editors see." },
                "fields": { "type": "array", "minItems": 1, "items": { "$ref": "#/$defs/item" }, "description": "The fields of every item of a list." }
            },
            "if": { "properties": { "type": { "const": "list" } } },
            "then": { "required": ["fields"] },
            "else": { "not": { "required": ["fields"] } }
        },
        "item": {
            "type": "object",
            "required": ["name", "type", "label"],
            "additionalProperties": false,
            "properties": {
                "name": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
                "type": { "enum": ["text", "textarea", "image", "link"] },
                "label": { "type": "string", "minLength": 1 }
            }
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Content/Enums/FieldTypeTest.php tests/Unit/Content/Actions/ParseStripTypeTest.php`
Expected: PASS (21 tests).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added` in `CHANGELOG.md`:

```markdown
- Strip types: the kinds of strips the pages of a site are built from, defined as JSON in the host (a `label` and `fields` of the types `text`, `textarea`, `image`, `link` and `list`, a list one level deep), with a JSON Schema for autocomplete in the editor (`resources/schema/strip.json`). `ParseStripType` reads one definition into the `StripType` and `Field` data objects (`FieldType` enum) and refuses a broken one with `InvalidStripType`, naming the field.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: Pint fixes nothing or only whitespace; PHPStan, type coverage and Pest are green.

```bash
git add src/Content/Enums/FieldType.php src/Content/Data/Field.php src/Content/Data/StripType.php src/Content/Exceptions/InvalidStripType.php src/Content/Actions/ParseStripType.php resources/schema/strip.json tests/Unit/Content/Enums/FieldTypeTest.php tests/Unit/Content/Actions/ParseStripTypeTest.php CHANGELOG.md
git commit -m "feat: define strip types with a label and typed fields"
```

---

### Task 2: Read the strip types of the site

**Files:**
- Modify: `config/made-cms-package.php` (append a `content` block)
- Create: `src/Content/Actions/ListStripTypes.php`
- Modify: `tests/Pest.php` (helper `useContentFolders()`)
- Create: `tests/Fixtures/content/strips/hero.json`
- Create: `tests/Fixtures/content/strips/cijfers.json`
- Test: `tests/Unit/Content/Actions/ListStripTypesTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: `ParseStripType::run(string $key, mixed $definition): StripType` and `InvalidStripType` (Task 1).
- Produces:
  - config keys `made-cms-package.content.strips_path` and `made-cms-package.content.pages_path`;
  - `ListStripTypes::run(): array<string, StripType>`, keyed and sorted by key, which throws `InvalidStripType`;
  - test helper `useContentFolders(bool $empty = false): string`, which returns the temporary root holding `strips/` and `pages/`.

- [ ] **Step 1: Add the fixtures and the test helper**

`tests/Fixtures/content/strips/hero.json`:

```json
{
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

`tests/Fixtures/content/strips/cijfers.json`:

```json
{
    "label": "Cijfers",
    "fields": [
        { "name": "items", "type": "list", "label": "Cijfers", "fields": [
            { "name": "value", "type": "text", "label": "Waarde" },
            { "name": "label", "type": "text", "label": "Omschrijving" }
        ] }
    ]
}
```

In `tests/Pest.php`, add these imports below the existing `use` lines:

```php
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
```

Append at the end of `tests/Pest.php`:

```php
/*
|--------------------------------------------------------------------------
| Content folders
|--------------------------------------------------------------------------
*/

/**
 * Points the strip and page folders of the content config at a fresh temporary folder, filled with the example
 * host files of tests/Fixtures/content unless $empty. Returns the root; delete it in afterEach.
 */
function useContentFolders(bool $empty = false): string
{
    $root = sys_get_temp_dir().'/made-cms-content-'.Str::random(12);

    File::ensureDirectoryExists("{$root}/strips");
    File::ensureDirectoryExists("{$root}/pages");

    if (! $empty) {
        File::copyDirectory(__DIR__.'/Fixtures/content', $root);
    }

    config()->set('made-cms-package.content.strips_path', "{$root}/strips");
    config()->set('made-cms-package.content.pages_path', "{$root}/pages");

    return $root;
}
```

- [ ] **Step 2: Write the failing test**

`tests/Unit/Content/Actions/ListStripTypesTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ListStripTypes;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('reads every strip type of the site, keyed and sorted by key', function (): void {
    $types = ListStripTypes::run();

    expect(array_keys($types))->toBe(['cijfers', 'hero'])
        ->and($types['hero']->label)->toBe('Hero')
        ->and($types['cijfers']->fields)->toEqual([
            new Field(name: 'items', type: FieldType::List, label: 'Cijfers', fields: [
                new Field(name: 'value', type: FieldType::Text, label: 'Waarde'),
                new Field(name: 'label', type: FieldType::Text, label: 'Omschrijving'),
            ]),
        ]);
});

it('has no strip types without a strips folder', function (): void {
    File::deleteDirectory($this->content.'/strips');

    expect(ListStripTypes::run())->toBe([]);
});

it('ignores files that are not json', function (): void {
    File::put($this->content.'/strips/README.md', 'De strooktypes van de site.');

    expect(array_keys(ListStripTypes::run()))->toBe(['cijfers', 'hero']);
});

it('names the strip type of a file that is not valid json', function (): void {
    File::put($this->content.'/strips/teams.json', '{"label": "Teams",');

    expect(fn () => ListStripTypes::run())->toThrow(InvalidStripType::class, 'Strip type [teams] is invalid: the file is not valid JSON');
});

it('refuses a definition that breaks a rule', function (): void {
    File::put($this->content.'/strips/teams.json', '{"label": "Teams", "fields": [{"name": "title", "type": "txt", "label": "Titel"}]}');

    expect(fn () => ListStripTypes::run())->toThrow(InvalidStripType::class, 'Strip type [teams] is invalid: field [title] has no known type');
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ListStripTypesTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Content\Actions\ListStripTypes" not found`.

- [ ] **Step 4: Write the implementation**

Append inside the returned array of `config/made-cms-package.php`, after the `launcher` block:

```php

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | The strip types a site is built from live in the host as JSON files,
    | one per type, next to the code that renders them: production only
    | reads them. pages_path holds the initial content of the pages;
    | `php artisan made-cms:pages` creates the pages that do not exist yet.
    |
    */

    'content' => [
        'strips_path' => resource_path('cms/strips'),
        'pages_path' => resource_path('cms/pages'),
    ],
```

`src/Content/Actions/ListStripTypes.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * The strip types of the site: one JSON file per type in the strips folder of the host, the file name is the key.
 * A site without the folder has no strip types yet.
 */
final class ListStripTypes
{
    use AsAction;

    /**
     * @return array<string, StripType> keyed and sorted by key
     *
     * @throws InvalidStripType
     */
    public function handle(): array
    {
        $types = [];

        foreach (glob(config()->string('made-cms-package.content.strips_path').'/*.json') ?: [] as $file) {
            $key = basename($file, '.json');

            try {
                $definition = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw InvalidStripType::because($key, 'the file is not valid JSON ('.$exception->getMessage().')');
            }

            $types[$key] = ParseStripType::run($key, $definition);
        }

        ksort($types);

        return $types;
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ListStripTypesTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Add the changelog line**

At the top of `### Added`:

```markdown
- `ListStripTypes` reads every strip type of the site from `resources/cms/strips/<key>.json`. Config keys `content.strips_path` and `content.pages_path` move the strip and page folders.
```

- [ ] **Step 7: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add config/made-cms-package.php src/Content/Actions/ListStripTypes.php tests/Pest.php tests/Fixtures/content tests/Unit/Content/Actions/ListStripTypesTest.php CHANGELOG.md
git commit -m "feat: read the strip types of the site from its json files"
```

---

### Task 3: Write a strip type

**Files:**
- Create: `src/Support/RelativePath.php`
- Create: `src/Content/Actions/SaveStripType.php`
- Test: `tests/Unit/Support/RelativePathTest.php`
- Test: `tests/Unit/Content/Actions/SaveStripTypeTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `ParseStripType::run()` and `InvalidStripType` (Task 1);
  - `ListStripTypes::run()` (Task 2, test only);
  - `useContentFolders()` (Task 2).
- Produces:
  - `RelativePath::between(string $from, string $to): string`;
  - `SaveStripType::run(StripType $type): string`, which returns the written file path and throws `InvalidStripType`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Support/RelativePathTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Support\RelativePath;

it('walks from a folder of the host to a file of the package', function (): void {
    expect(RelativePath::between('/var/www/site/resources/cms/strips', '/var/www/site/vendor/made-foryou/made-cms-package/resources/schema/strip.json'))
        ->toBe('../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json');
});

it('points at a file in the same folder by its name', function (): void {
    expect(RelativePath::between('/var/www/site/schema/', '/var/www/site/schema/strip.json'))->toBe('strip.json');
});

it('accepts windows separators', function (): void {
    expect(RelativePath::between('C:\\sites\\ttv\\resources\\cms\\strips', 'C:\\sites\\ttv\\vendor\\made-foryou\\made-cms-package\\resources\\schema\\strip.json'))
        ->toBe('../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json');
});
```

`tests/Unit/Content/Actions/SaveStripTypeTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ListStripTypes;
use Made\CmsPackage\Content\Actions\SaveStripType;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

beforeEach(function (): void {
    $this->content = useContentFolders(empty: true);

    $this->teams = new StripType(key: 'teams', label: 'Teams', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'teams', type: FieldType::List, label: 'Teams', fields: [
            new Field(name: 'name', type: FieldType::Text, label: 'Naam'),
            new Field(name: 'results', type: FieldType::Link, label: 'Uitslagen'),
        ]),
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('writes the strip type to its file in the strips folder', function (): void {
    $file = SaveStripType::run($this->teams);

    expect($file)->toBe($this->content.'/strips/teams.json')
        ->and(File::exists($file))->toBeTrue();
});

it('writes a file that reads back as the same strip type', function (): void {
    SaveStripType::run($this->teams);

    expect(ListStripTypes::run()['teams'])->toEqual($this->teams);
});

it('writes the keys in a fixed order, pretty printed and with a schema reference', function (): void {
    SaveStripType::run($this->teams);

    $contents = File::get($this->content.'/strips/teams.json');
    $definition = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($definition))->toBe(['$schema', 'label', 'fields'])
        ->and(array_keys($definition['fields'][0]))->toBe(['name', 'type', 'label'])
        ->and(array_keys($definition['fields'][1]))->toBe(['name', 'type', 'label', 'fields'])
        ->and($definition['$schema'])->toEndWith('vendor/made-foryou/made-cms-package/resources/schema/strip.json')
        ->and($contents)->toContain("\n    \"label\": \"Teams\",\n")
        ->and($contents)->toEndWith("}\n");
});

it('creates the strips folder when the site has none yet', function (): void {
    File::deleteDirectory($this->content.'/strips');

    SaveStripType::run($this->teams);

    expect(File::exists($this->content.'/strips/teams.json'))->toBeTrue();
});

it('refuses a key that could leave the strips folder, without writing anything', function (): void {
    expect(fn () => SaveStripType::run(new StripType(key: '../evil', label: 'Evil', fields: [])))
        ->toThrow(InvalidStripType::class, 'the key is not written in kebab-case');

    expect(File::exists($this->content.'/evil.json'))->toBeFalse()
        ->and(File::files($this->content.'/strips'))->toBe([]);
});

it('refuses a strip type that would not read back', function (): void {
    $type = new StripType(key: 'cijfers', label: 'Cijfers', fields: [
        new Field(name: 'items', type: FieldType::List, label: 'Cijfers', fields: []),
    ]);

    expect(fn () => SaveStripType::run($type))->toThrow(InvalidStripType::class, 'field [items] needs at least one field');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Support/RelativePathTest.php tests/Unit/Content/Actions/SaveStripTypeTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Support\RelativePath" not found`.

- [ ] **Step 3: Write the implementation**

`src/Support/RelativePath.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * Relative paths between files of the host, e.g. from a strip type file to the JSON Schema in the package.
 */
final class RelativePath
{
    /**
     * The path to $to as seen from the folder $from. Both are absolute; Windows separators are accepted.
     */
    public static function between(string $from, string $to): string
    {
        $from = self::parts($from);
        $to = self::parts($to);

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)).implode('/', $to);
    }

    /**
     * @return list<string>
     */
    private static function parts(string $path): array
    {
        return array_values(array_filter(
            explode('/', str_replace('\\', '/', $path)),
            fn (string $part): bool => $part !== '' && $part !== '.',
        ));
    }
}
```

`src/Content/Actions/SaveStripType.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Support\RelativePath;

/**
 * Writes a strip type to its JSON file in the strips folder of the host. Definitions are code: only the local
 * setup of a site writes them. The file is checked by the rules of reading first, so it always reads back,
 * and written in a fixed order, so a change shows up as a small diff.
 */
final class SaveStripType
{
    use AsAction;

    /**
     * @return string the path of the written file
     *
     * @throws InvalidStripType
     */
    public function handle(StripType $type): string
    {
        $folder = config()->string('made-cms-package.content.strips_path');

        $definition = [
            '$schema' => RelativePath::between($folder, base_path('vendor/made-foryou/made-cms-package/resources/schema/strip.json')),
            'label' => $type->label,
            'fields' => $this->fields($type->fields),
        ];

        // Also refuses a key such as "../evil" before it becomes part of a path.
        ParseStripType::run($type->key, $definition);

        $file = "{$folder}/{$type->key}.json";

        File::ensureDirectoryExists($folder);
        File::put($file, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        return $file;
    }

    /**
     * @param  list<Field>  $fields
     * @return list<array<string, mixed>>
     */
    private function fields(array $fields): array
    {
        return array_map(function (Field $field): array {
            $definition = ['name' => $field->name, 'type' => $field->type->value, 'label' => $field->label];

            if ($field->type === FieldType::List) {
                $definition['fields'] = $this->fields($field->fields);
            }

            return $definition;
        }, $fields);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Support/RelativePathTest.php tests/Unit/Content/Actions/SaveStripTypeTest.php`
Expected: PASS (9 tests).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- `SaveStripType` writes a strip type to its JSON file, pretty printed in a fixed key order with a `$schema` reference, after checking it by the same rules as reading.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green (the Support arch test also covers `RelativePath`).

```bash
git add src/Support/RelativePath.php src/Content/Actions/SaveStripType.php tests/Unit/Support/RelativePathTest.php tests/Unit/Content/Actions/SaveStripTypeTest.php CHANGELOG.md
git commit -m "feat: write strip types to their json files"
```

---

### Task 4: Pages by path, with their strips

**Files:**
- Modify: `database/migrations/2026_01_01_000100_create_cms_pages_table.php` (`slug` → `path`; `cms_pages` is still unreleased)
- Create: `database/migrations/2026_01_01_000150_create_cms_strips_table.php`
- Create: `src/Support/PagePath.php`
- Modify: `src/Content/Builders/PageBuilder.php` (add `forPath()`)
- Modify: `src/Content/Models/Page.php` (`path`, `strips()`)
- Create: `src/Content/Builders/StripBuilder.php`
- Create: `src/Content/Models/Strip.php`
- Modify: `database/factories/Content/PageFactory.php`
- Create: `database/factories/Content/StripFactory.php`
- Modify: `workbench/database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/Support/PagePathTest.php`
- Test: `tests/Feature/Content/Builders/PageBuilderTest.php` (add tests)
- Test: `tests/Feature/Content/Builders/StripBuilderTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - `PagePath::normalise(string $path): string`;
  - `PageBuilder::forPath(string $path): PageBuilder`;
  - `Page::strips(): HasMany<Strip, Page>`, ordered by `position`;
  - model `Strip` (`cms_strips`) with `id`, `page_id`, `type`, `position`, `content` (`array<string, mixed>`), relation `page()` and builder `StripBuilder`;
  - `Strip::toArray()` returns exactly `['id', 'type', 'content']`;
  - `Strip::factory()` and `Page::factory()`, the latter now taking `path`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Support/PagePathTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Support\PagePath;

it('writes a path one way, however the request gives it', function (string $path, string $normalised): void {
    expect(PagePath::normalise($path))->toBe($normalised);
})->with([
    'home' => ['/', '/'],
    'home without slash' => ['', '/'],
    'leading slash' => ['/lid-worden', '/lid-worden'],
    'without slashes' => ['lid-worden', '/lid-worden'],
    'trailing slash' => ['/lid-worden/', '/lid-worden'],
    'nested' => ['teams/heren-1', '/teams/heren-1'],
]);
```

Append to `tests/Feature/Content/Builders/PageBuilderTest.php`:

```php
it('finds a page by its path, however the path is written', function (string $path): void {
    $page = Page::factory()->create(['path' => '/lid-worden']);
    Page::factory()->create(['path' => '/']);

    expect(Page::query()->forPath($path)->sole()->is($page))->toBeTrue();
})->with(['/lid-worden', 'lid-worden', '/lid-worden/']);

it('finds the home page by its path', function (string $path): void {
    $home = Page::factory()->create(['path' => '/']);
    Page::factory()->create(['path' => '/lid-worden']);

    expect(Page::query()->forPath($path)->sole()->is($home))->toBeTrue();
})->with(['/', '']);
```

`tests/Feature/Content/Builders/StripBuilderTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\StripBuilder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

it('uses the custom builder for strip queries', function (): void {
    expect(Strip::query())->toBeInstanceOf(StripBuilder::class);
});

it('gives a page its strips in the order they show', function (): void {
    $page = Page::factory()->create();
    $second = Strip::factory()->for($page)->create(['position' => 1]);
    $first = Strip::factory()->for($page)->create(['position' => 0]);
    Strip::factory()->create(['position' => 0]);

    expect($page->strips->modelKeys())->toBe([$first->id, $second->id]);
});

it('serialises a strip to its id, type and content only', function (): void {
    $strip = Strip::factory()->create(['type' => 'hero', 'content' => ['title' => 'TTV Merwestad']]);

    expect($strip->refresh()->toArray())->toBe([
        'id' => $strip->id,
        'type' => 'hero',
        'content' => ['title' => 'TTV Merwestad'],
    ]);
});

it('removes the strips together with their page', function (): void {
    $strip = Strip::factory()->create();

    $strip->page->delete();

    expect(Strip::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Support/PagePathTest.php tests/Feature/Content/Builders`
Expected: FAIL with `Class "Made\CmsPackage\Support\PagePath" not found`, and in `PageBuilderTest` with `table cms_pages has no column named path`.

- [ ] **Step 3: Write the implementation**

In `database/migrations/2026_01_01_000100_create_cms_pages_table.php`, replace

```php
            $table->string('slug')->unique();
```

with

```php
            $table->string('path')->unique();
```

`database/migrations/2026_01_01_000150_create_cms_strips_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_strips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position');
            $table->json('content');
            $table->timestamps();

            $table->index(['page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_strips');
    }
};
```

`src/Support/PagePath.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * The path of a page of the site, written one way: a leading slash, no trailing slash, "/" for the home page.
 */
final class PagePath
{
    /**
     * "lid-worden", "/lid-worden/" and "/lid-worden" all become "/lid-worden", so $request->path() finds the page.
     */
    public static function normalise(string $path): string
    {
        return '/'.trim($path, '/');
    }
}
```

In `src/Content/Builders/PageBuilder.php`, add `use Made\CmsPackage\Support\PagePath;` to the imports and add this method after `draft()`:

```php
    /**
     * The page at a path of the site; "lid-worden", "/lid-worden" and "/lid-worden/" are the same page.
     */
    public function forPath(string $path): self
    {
        return $this->where('path', PagePath::normalise($path));
    }
```

`src/Content/Builders/StripBuilder.php`:

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
final class StripBuilder extends Builder {}
```

`src/Content/Models/Strip.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\StripBuilder;
use Made\CmsPackage\Database\Factories\Content\StripFactory;

/**
 * One strip of a page: a strip type of the site with its content. Serialises to {id, type, content},
 * so a host passes $page->strips straight to Blade, Inertia or a JSON response.
 *
 * @property int $id
 * @property int $page_id
 * @property string $type
 * @property int $position
 * @property array<string, mixed> $content
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 *
 * @method static StripBuilder query()
 */
class Strip extends Model
{
    /** @use HasFactory<StripFactory> */
    use HasFactory;

    protected $table = 'cms_strips';

    protected $fillable = [
        'page_id',
        'type',
        'position',
        'content',
    ];

    protected $hidden = [
        'page_id',
        'position',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): StripBuilder
    {
        return new StripBuilder($query);
    }

    protected static function newFactory(): StripFactory
    {
        return StripFactory::new();
    }
}
```

Replace `src/Content/Models/Page.php` with:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Database\Factories\Content\PageFactory;

/**
 * A page of the site that editors manage: its path on the site and the strips it is built from.
 *
 * @property int $id
 * @property string $title
 * @property string $path
 * @property PageStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Strip> $strips
 *
 * @method static PageBuilder query()
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $table = 'cms_pages';

    protected $fillable = [
        'title',
        'path',
        'status',
        'published_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * The strips of the page, in the order they show.
     *
     * @return HasMany<Strip, $this>
     */
    public function strips(): HasMany
    {
        return $this->hasMany(Strip::class)->orderBy('position');
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder(mixed $query): PageBuilder
    {
        return new PageBuilder($query);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
```

In `database/factories/Content/PageFactory.php`, replace

```php
            'slug' => Str::slug($title),
```

with

```php
            'path' => '/'.Str::slug($title),
```

`database/factories/Content/StripFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

/**
 * @extends Factory<Strip>
 */
final class StripFactory extends Factory
{
    protected $model = Strip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'type' => 'hero',
            'position' => 0,
            'content' => ['title' => fake()->sentence(3)],
        ];
    }
}
```

In `workbench/database/seeders/DatabaseSeeder.php`, replace

```php
            'slug' => 'home',
```

with

```php
            'path' => '/',
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Support/PagePathTest.php tests/Feature/Content`
Expected: PASS, including the existing `PublishPageTest`.

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- Pages are found by their `path` (`cms_pages.path`, unique; `PageBuilder::forPath()` treats `lid-worden`, `/lid-worden` and `/lid-worden/` as the same page) and hold their strips in order: the `cms_strips` table and `Strip` model (`Page::strips()`), each strip with its type and content, serialised to `{id, type, content}`.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green; the persistence arch test confirms that `Strip` declares its own builder and factory.

```bash
git add database/migrations src/Support/PagePath.php src/Content/Builders src/Content/Models database/factories/Content workbench/database/seeders/DatabaseSeeder.php tests/Unit/Support/PagePathTest.php tests/Feature/Content/Builders CHANGELOG.md
git commit -m "feat: give pages a path and an ordered list of strips"
```

---

### Task 5: Check the content of a strip

**Files:**
- Create: `src/Content/Exceptions/InvalidStripContent.php`
- Create: `src/Content/Actions/ValidateStripContent.php`
- Test: `tests/Unit/Content/Actions/ValidateStripContentTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: `StripType`, `Field` and `FieldType` (Task 1).
- Produces:
  - `ValidateStripContent::run(StripType $type, mixed $content): array<string, mixed>`. It returns every field of the type in definition order, fills a missing value with `''`, `null` or `[]`, fills a missing `alt` or `url` with `''`, and throws `InvalidStripContent`.
  - `InvalidStripContent::because(string $type, string $path, string $reason): self`
  - `InvalidStripContent::unknownType(string $type): self`, with the message `Strip type [{type}] has no definition in the strips folder.`

- [ ] **Step 1: Write the failing test**

`tests/Unit/Content/Actions/ValidateStripContentTest.php`:

```php
<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ValidateStripContent;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;

beforeEach(function (): void {
    $this->hero = new StripType(key: 'hero', label: 'Hero', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'intro', type: FieldType::Textarea, label: 'Introductie'),
        new Field(name: 'photo', type: FieldType::Image, label: 'Foto'),
        new Field(name: 'buttons', type: FieldType::List, label: 'Knoppen', fields: [
            new Field(name: 'link', type: FieldType::Link, label: 'Knop'),
        ]),
    ]);
});

it('keeps the content, in the order of the fields', function (): void {
    expect(ValidateStripContent::run($this->hero, [
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '#lid-worden']]],
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => 'De sporthal'],
        'intro' => "Sinds 1982.\nVoor iedereen.",
        'title' => 'TTV Merwestad',
    ]))->toBe([
        'title' => 'TTV Merwestad',
        'intro' => "Sinds 1982.\nVoor iedereen.",
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => 'De sporthal'],
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '#lid-worden']]],
    ]);
});

it('fills a field without a value with an empty value', function (): void {
    expect(ValidateStripContent::run($this->hero, []))->toBe([
        'title' => '',
        'intro' => '',
        'photo' => null,
        'buttons' => [],
    ]);
});

it('fills a missing alt text or link url with empty text', function (): void {
    expect(ValidateStripContent::run($this->hero, [
        'photo' => ['src' => '/photos/hal.jpg'],
        'buttons' => [['link' => ['label' => 'Lid worden']]],
    ]))->toMatchArray([
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => ''],
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '']]],
    ]);
});

it('refuses content that does not fit the strip type', function (mixed $content, string $message): void {
    expect(fn () => ValidateStripContent::run($this->hero, $content))->toThrow(InvalidStripContent::class, $message);
})->with([
    'a list instead of an object' => [['TTV Merwestad'], 'Content of strip type [hero] is invalid: expected an object with the fields of the strip'],
    'no content at all' => [null, 'Content of strip type [hero] is invalid: expected an object'],
    'an unknown field' => [['subtitle' => 'Dordrecht'], 'is invalid at [subtitle]: this field is not in the definition'],
    'a number as text' => [['title' => 1982], 'is invalid at [title]: expected text'],
    'an image as text' => [['photo' => '/photos/hal.jpg'], 'is invalid at [photo]: expected {src, alt}'],
    'an image with an extra key' => [['photo' => ['src' => '/photos/hal.jpg', 'width' => 1200]], 'is invalid at [photo]: expected {src, alt}'],
    'a list as an object' => [['buttons' => ['link' => ['label' => 'Lid worden', 'url' => '#']]], 'is invalid at [buttons]: expected a list of items'],
    'a broken link in a list' => [['buttons' => [['link' => 'Lid worden']]], 'is invalid at [buttons.0.link]: expected {label, url}'],
]);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ValidateStripContentTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Content\Actions\ValidateStripContent" not found`.

- [ ] **Step 3: Write the implementation**

`src/Content/Exceptions/InvalidStripContent.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidStripContent extends RuntimeException
{
    public static function because(string $type, string $path, string $reason): self
    {
        return new self($path === ''
            ? "Content of strip type [{$type}] is invalid: {$reason}."
            : "Content of strip type [{$type}] is invalid at [{$path}]: {$reason}.");
    }

    public static function unknownType(string $type): self
    {
        return new self("Strip type [{$type}] has no definition in the strips folder.");
    }
}
```

`src/Content/Actions/ValidateStripContent.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;

/**
 * Checks the content of a strip against its type and returns it with every field of the type, in the order
 * of the definition. A field without a value gets an empty one, so a frontend never meets a missing key.
 */
final class ValidateStripContent
{
    use AsAction;

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidStripContent
     */
    public function handle(StripType $type, mixed $content): array
    {
        return $this->group($type->key, $type->fields, $content, '');
    }

    /**
     * The values of a group of fields: the strip itself, or one item of a list.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    private function group(string $type, array $fields, mixed $content, string $path): array
    {
        if (! is_array($content) || ($content !== [] && array_is_list($content))) {
            throw InvalidStripContent::because($type, $path, 'expected an object with the fields of the strip');
        }

        $names = array_map(fn (Field $field): string => $field->name, $fields);

        foreach (array_keys($content) as $name) {
            if (! in_array($name, $names, true)) {
                throw InvalidStripContent::because($type, $this->path($path, (string) $name), 'this field is not in the definition');
            }
        }

        $values = [];

        foreach ($fields as $field) {
            $values[$field->name] = $this->value($type, $field, $content[$field->name] ?? null, $this->path($path, $field->name));
        }

        return $values;
    }

    private function value(string $type, Field $field, mixed $value, string $path): mixed
    {
        return match ($field->type) {
            FieldType::Text, FieldType::Textarea => $this->text($type, $value, $path),
            FieldType::Image => $this->pair($type, $value, $path, ['src', 'alt']),
            FieldType::Link => $this->pair($type, $value, $path, ['label', 'url']),
            FieldType::List => $this->items($type, $field, $value, $path),
        };
    }

    private function text(string $type, mixed $value, string $path): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            throw InvalidStripContent::because($type, $path, 'expected text');
        }

        return $value;
    }

    /**
     * An image ({src, alt}) or a link ({label, url}): two texts, of which a missing one stays empty.
     *
     * @param  list<string>  $keys
     * @return array<string, string>|null
     */
    private function pair(string $type, mixed $value, string $path, array $keys): ?array
    {
        if ($value === null) {
            return null;
        }

        $expected = 'expected {'.implode(', ', $keys).'}';

        if (! is_array($value) || array_diff(array_keys($value), $keys) !== []) {
            throw InvalidStripContent::because($type, $path, $expected);
        }

        $pair = [];

        foreach ($keys as $key) {
            $text = $value[$key] ?? '';

            if (! is_string($text)) {
                throw InvalidStripContent::because($type, $path, $expected);
            }

            $pair[$key] = $text;
        }

        return $pair;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $type, Field $field, mixed $value, string $path): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidStripContent::because($type, $path, 'expected a list of items');
        }

        $items = [];

        foreach ($value as $index => $item) {
            $items[] = $this->group($type, $field->fields, $item, $this->path($path, (string) $index));
        }

        return $items;
    }

    private function path(string $parent, string $name): string
    {
        return $parent === '' ? $name : "{$parent}.{$name}";
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ValidateStripContentTest.php`
Expected: PASS (11 tests).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- `ValidateStripContent` checks the content of a strip against its type, fills fields without a value with an empty value and refuses what does not fit with `InvalidStripContent`, naming the field.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add src/Content/Exceptions/InvalidStripContent.php src/Content/Actions/ValidateStripContent.php tests/Unit/Content/Actions/ValidateStripContentTest.php CHANGELOG.md
git commit -m "feat: check the content of a strip against its type"
```

---

### Task 6: Read the page files of the site

**Files:**
- Create: `src/Content/Data/PageImport.php`
- Create: `src/Content/Data/StripImport.php`
- Create: `src/Content/Exceptions/InvalidPageImport.php`
- Create: `src/Content/Actions/ListPageImports.php`
- Create: `resources/schema/page.json`
- Create: `tests/Fixtures/content/pages/home.json`
- Test: `tests/Unit/Content/Actions/ListPageImportsTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `PagePath::normalise()` (Task 4);
  - config key `content.pages_path` and `useContentFolders()` (Task 2).
- Produces:
  - `final readonly class PageImport(string $path, string $title, list<StripImport> $strips)`, where `$path` is already normalised;
  - `final readonly class StripImport(string $type, array<array-key, mixed> $content = [])`;
  - `InvalidPageImport::because(string $file, string $reason): self`, with the message `Page file [{file}] is invalid: {reason}.`;
  - `ListPageImports::run(): list<PageImport>`, sorted by file name, which throws `InvalidPageImport`.

- [ ] **Step 1: Add the fixture**

`tests/Fixtures/content/pages/home.json`:

```json
{
    "path": "/",
    "title": "Home",
    "strips": [
        {
            "type": "hero",
            "content": {
                "eyebrow": "Tafeltennisvereniging in Dordrecht",
                "title": "TTV Merwestad",
                "intro": "Sinds 1982 dé plek voor tafeltennissers van alle niveaus.",
                "photo": { "src": "/photos/hal.jpg", "alt": "De sporthal" },
                "buttons": [ { "link": { "label": "Lid worden", "url": "#lid-worden" } } ]
            }
        },
        {
            "type": "cijfers",
            "content": {
                "items": [
                    { "value": "60+", "label": "Jaar actief" },
                    { "value": "35+", "label": "Leden" }
                ]
            }
        }
    ]
}
```

- [ ] **Step 2: Write the failing test**

`tests/Unit/Content/Actions/ListPageImportsTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ListPageImports;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Data\StripImport;
use Made\CmsPackage\Content\Exceptions\InvalidPageImport;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('reads the initial content of a page', function (): void {
    $home = ListPageImports::run()[0];

    expect($home->path)->toBe('/')
        ->and($home->title)->toBe('Home')
        ->and(array_map(fn (StripImport $strip): string => $strip->type, $home->strips))->toBe(['hero', 'cijfers'])
        ->and($home->strips[1]->content)->toBe(['items' => [
            ['value' => '60+', 'label' => 'Jaar actief'],
            ['value' => '35+', 'label' => 'Leden'],
        ]]);
});

it('reads the pages in the order of their file names', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"path": "/lid-worden", "title": "Lid worden", "strips": []}');
    File::put($this->content.'/pages/contact.json', '{"path": "/contact", "title": "Contact", "strips": []}');

    expect(array_map(fn (PageImport $page): string => $page->path, ListPageImports::run()))->toBe(['/contact', '/', '/lid-worden']);
});

it('writes the path of a page the way the site asks for it', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"path": "/lid-worden/", "title": "Lid worden", "strips": []}');

    expect(ListPageImports::run()[1]->path)->toBe('/lid-worden');
});

it('gives a strip without content empty content', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"path": "/lid-worden", "title": "Lid worden", "strips": [{"type": "hero"}]}');

    expect(ListPageImports::run()[1]->strips[0]->content)->toBe([]);
});

it('has no pages without a pages folder', function (): void {
    File::deleteDirectory($this->content.'/pages');

    expect(ListPageImports::run())->toBe([]);
});

it('refuses a page file that breaks a rule', function (string $json, string $message): void {
    File::put($this->content.'/pages/broken.json', $json);

    expect(fn () => ListPageImports::run())->toThrow(InvalidPageImport::class, $message);
})->with([
    'invalid json' => ['{"path": "/"', 'Page file [broken.json] is invalid: the file is not valid JSON'],
    'not an object' => ['"Home"', 'the file is not an object with a path, a title and strips'],
    'an unknown key' => ['{"path": "/x", "title": "X", "strips": [], "slug": "x"}', 'unknown key [slug]'],
    'a path without slash' => ['{"path": "x", "title": "X", "strips": []}', 'the path must start with a slash'],
    'no title' => ['{"path": "/x", "strips": []}', 'the page needs a title'],
    'strips as an object' => ['{"path": "/x", "title": "X", "strips": {"hero": {}}}', 'the strips must be a list'],
    'a strip without type' => ['{"path": "/x", "title": "X", "strips": [{"content": {}}]}', 'strip 0 needs a type, with only its content next to it'],
    'content as text' => ['{"path": "/x", "title": "X", "strips": [{"type": "hero", "content": "TTV"}]}', 'the content of strip 0 must be an object'],
]);
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ListPageImportsTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Content\Actions\ListPageImports" not found`.

- [ ] **Step 4: Write the implementation**

`src/Content/Data/PageImport.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The initial content of one page, read from resources/cms/pages/<page>.json in the host.
 */
final readonly class PageImport
{
    /**
     * @param  list<StripImport>  $strips
     */
    public function __construct(
        public string $path,
        public string $title,
        public array $strips,
    ) {}
}
```

`src/Content/Data/StripImport.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip of a page file: its type and its content, checked against the strip type on import.
 */
final readonly class StripImport
{
    /**
     * @param  array<array-key, mixed>  $content
     */
    public function __construct(
        public string $type,
        public array $content = [],
    ) {}
}
```

`src/Content/Exceptions/InvalidPageImport.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidPageImport extends RuntimeException
{
    public static function because(string $file, string $reason): self
    {
        return new self("Page file [{$file}] is invalid: {$reason}.");
    }
}
```

`src/Content/Actions/ListPageImports.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\File;
use JsonException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Data\StripImport;
use Made\CmsPackage\Content\Exceptions\InvalidPageImport;
use Made\CmsPackage\Support\PagePath;

/**
 * The initial content of the pages of the site: one JSON file per page in the pages folder of the host,
 * in the order of their file names. Whether the strips fit their types is checked on import.
 */
final class ListPageImports
{
    use AsAction;

    /**
     * @return list<PageImport>
     *
     * @throws InvalidPageImport
     */
    public function handle(): array
    {
        $files = glob(config()->string('made-cms-package.content.pages_path').'/*.json') ?: [];

        sort($files);

        return array_map($this->read(...), $files);
    }

    private function read(string $file): PageImport
    {
        $name = basename($file);

        try {
            $page = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidPageImport::because($name, 'the file is not valid JSON ('.$exception->getMessage().')');
        }

        if (! is_array($page)) {
            throw InvalidPageImport::because($name, 'the file is not an object with a path, a title and strips');
        }

        foreach (array_keys($page) as $key) {
            if (! in_array($key, ['$schema', 'path', 'title', 'strips'], true)) {
                throw InvalidPageImport::because($name, "unknown key [{$key}]");
            }
        }

        $path = $page['path'] ?? null;
        $title = $page['title'] ?? null;
        $strips = $page['strips'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, '/')) {
            throw InvalidPageImport::because($name, 'the path must start with a slash, e.g. "/" or "/lid-worden"');
        }

        if (! is_string($title) || trim($title) === '') {
            throw InvalidPageImport::because($name, 'the page needs a title');
        }

        if (! is_array($strips) || ! array_is_list($strips)) {
            throw InvalidPageImport::because($name, 'the strips must be a list');
        }

        $imports = [];

        foreach ($strips as $index => $strip) {
            $imports[] = $this->strip($name, $index, $strip);
        }

        return new PageImport(path: PagePath::normalise($path), title: trim($title), strips: $imports);
    }

    private function strip(string $name, int $index, mixed $strip): StripImport
    {
        if (! is_array($strip) || ! is_string($strip['type'] ?? null) || array_diff(array_keys($strip), ['type', 'content']) !== []) {
            throw InvalidPageImport::because($name, "strip {$index} needs a type, with only its content next to it");
        }

        $content = $strip['content'] ?? [];

        if (! is_array($content)) {
            throw InvalidPageImport::because($name, "the content of strip {$index} must be an object");
        }

        return new StripImport(type: $strip['type'], content: $content);
    }
}
```

`resources/schema/page.json`:

```json
{
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "title": "Made CMS page",
    "description": "The initial content of a page: its path, its title and its strips in order. `php artisan made-cms:pages` creates the page when the site has no page at that path yet.",
    "type": "object",
    "required": ["path", "title", "strips"],
    "additionalProperties": false,
    "properties": {
        "$schema": { "type": "string" },
        "path": { "type": "string", "pattern": "^/", "description": "The path of the page on the site, e.g. \"/\" or \"/lid-worden\"." },
        "title": { "type": "string", "minLength": 1 },
        "strips": {
            "type": "array",
            "items": {
                "type": "object",
                "required": ["type"],
                "additionalProperties": false,
                "properties": {
                    "type": { "type": "string", "pattern": "^[a-z0-9]+(?:-[a-z0-9]+)*$", "description": "The key of a strip type in resources/cms/strips." },
                    "content": { "type": "object", "description": "The values of the fields of the strip type." }
                }
            }
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Content/Actions/ListPageImportsTest.php`
Expected: PASS (13 tests).

- [ ] **Step 6: Add the changelog line**

At the top of `### Added`:

```markdown
- Page files: the initial content of a page in `resources/cms/pages/<page>.json` (`path`, `title` and its `strips` with their content), with a JSON Schema (`resources/schema/page.json`). `ListPageImports` reads them into the `PageImport` and `StripImport` data objects and refuses a broken file with `InvalidPageImport`.
```

- [ ] **Step 7: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add src/Content/Data/PageImport.php src/Content/Data/StripImport.php src/Content/Exceptions/InvalidPageImport.php src/Content/Actions/ListPageImports.php resources/schema/page.json tests/Fixtures/content/pages tests/Unit/Content/Actions/ListPageImportsTest.php CHANGELOG.md
git commit -m "feat: read the initial content of pages from their json files"
```

---

### Task 7: Import a page

**Files:**
- Create: `src/Content/Actions/ImportPage.php`
- Test: `tests/Feature/Content/Actions/ImportPageTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `ListStripTypes::run()` (Task 2);
  - `ValidateStripContent::run()` and `InvalidStripContent` (Task 5);
  - `PageImport` and `StripImport` (Task 6);
  - `Page`, `Page::strips()`, `PageBuilder::forPath()`, `Strip` and the factories (Task 4).
- Produces: `ImportPage::run(PageImport $import): bool`. It returns `true` when the page was created and `false` when a page with that path already existed. It throws `InvalidStripType` and `InvalidStripContent`, and writes nothing when it throws.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Content/Actions/ImportPageTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ImportPage;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Data\StripImport;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('creates a published page with its strips in order', function (): void {
    $this->freezeSecond();

    $created = ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
        new StripImport(type: 'cijfers', content: ['items' => [['value' => '60+', 'label' => 'Jaar actief']]]),
    ]));

    $page = Page::query()->forPath('/')->sole();

    expect($created)->toBeTrue()
        ->and($page->title)->toBe('Home')
        ->and($page->status)->toBe(PageStatus::Published)
        ->and($page->published_at?->equalTo(now()))->toBeTrue()
        ->and($page->strips->toArray())->toBe([
            ['id' => $page->strips[0]->id, 'type' => 'hero', 'content' => [
                'eyebrow' => '',
                'title' => 'TTV Merwestad',
                'intro' => '',
                'photo' => null,
                'buttons' => [],
            ]],
            ['id' => $page->strips[1]->id, 'type' => 'cijfers', 'content' => [
                'items' => [['value' => '60+', 'label' => 'Jaar actief']],
            ]],
        ]);
});

it('leaves a page that already exists alone', function (): void {
    $page = Page::factory()->create(['path' => '/', 'title' => 'Welkom']);
    Strip::factory()->for($page)->create(['type' => 'hero', 'content' => ['title' => 'Bewerkt in het CMS']]);

    $created = ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
    ]));

    expect($created)->toBeFalse()
        ->and(Page::query()->count())->toBe(1)
        ->and($page->refresh()->title)->toBe('Welkom')
        ->and($page->strips->sole()->content)->toBe(['title' => 'Bewerkt in het CMS']);
});

it('refuses a strip type the site does not have, without creating anything', function (): void {
    expect(fn () => ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero'),
        new StripImport(type: 'teams'),
    ])))->toThrow(InvalidStripContent::class, 'Strip type [teams] has no definition in the strips folder');

    expect(Page::query()->count())->toBe(0)
        ->and(Strip::query()->count())->toBe(0);
});

it('refuses content that does not fit its strip type, without creating anything', function (): void {
    expect(fn () => ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
        new StripImport(type: 'cijfers', content: ['items' => 'veel']),
    ])))->toThrow(InvalidStripContent::class, 'Content of strip type [cijfers] is invalid at [items]: expected a list of items');

    expect(Page::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Content/Actions/ImportPageTest.php`
Expected: FAIL with `Class "Made\CmsPackage\Content\Actions\ImportPage" not found`.

- [ ] **Step 3: Write the implementation**

`src/Content/Actions/ImportPage.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Page;

/**
 * Creates a page with its strips from its page file, unless the site already has a page at that path:
 * content edited in the CMS is never overwritten. Every strip is checked before anything is written.
 */
final class ImportPage
{
    use AsAction;

    /**
     * @return bool whether the page was created
     *
     * @throws InvalidStripType
     * @throws InvalidStripContent
     */
    public function handle(PageImport $import): bool
    {
        if (Page::query()->forPath($import->path)->exists()) {
            return false;
        }

        $types = ListStripTypes::run();
        $strips = [];

        foreach ($import->strips as $position => $strip) {
            $type = $types[$strip->type] ?? throw InvalidStripContent::unknownType($strip->type);

            $strips[] = [
                'type' => $strip->type,
                'position' => $position,
                'content' => ValidateStripContent::run($type, $strip->content),
            ];
        }

        DB::transaction(function () use ($import, $strips): void {
            $page = Page::query()->create([
                'title' => $import->title,
                'path' => $import->path,
                'status' => PageStatus::Published,
                'published_at' => now(),
            ]);

            $page->strips()->createMany($strips);
        });

        return true;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Content/Actions/ImportPageTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- `ImportPage` creates a published page with its strips from a page file, only when the site has no page at that path yet, and checks every strip before it writes anything.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green.

```bash
git add src/Content/Actions/ImportPage.php tests/Feature/Content/Actions/ImportPageTest.php CHANGELOG.md
git commit -m "feat: import a page from its page file without touching existing pages"
```

---

### Task 8: The `made-cms:pages` command

**Files:**
- Create: `src/Console/Commands/ImportPagesCommand.php`
- Modify: `src/CmsPackageServiceProvider.php` (register the command)
- Modify: `lang/nl/console.php`
- Modify: `lang/en/console.php`
- Test: `tests/Feature/Console/Commands/ImportPagesCommandTest.php`
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes:
  - `ListPageImports::run()` and `InvalidPageImport` (Task 6);
  - `ImportPage::run()` (Task 7);
  - `InvalidStripType` (Task 1) and `InvalidStripContent` (Task 5).
- Produces: the artisan command `made-cms:pages`.
  - It exits `0` when every page was created or skipped.
  - It exits `1` when a page file cannot be read, and then creates nothing.
  - It exits `1` when a page's strips do not fit, but still creates the other pages.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Console/Commands/ImportPagesCommandTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('creates the pages that do not exist yet', function (): void {
    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('aangemaakt met 2 stroken')
        ->assertSuccessful();

    expect(Page::query()->forPath('/')->sole()->strips)->toHaveCount(2);
});

it('skips a page that already exists, so it can run on every deploy', function (): void {
    $this->artisan('made-cms:pages')->assertSuccessful();

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('bestaat al, overgeslagen')
        ->assertSuccessful();

    expect(Page::query()->count())->toBe(1);
});

it('says so when there are no page files', function (): void {
    File::deleteDirectory($this->content.'/pages');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('Geen paginabestanden gevonden')
        ->assertSuccessful();
});

it('fails on a page file it cannot read, before creating anything', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"path": "lid-worden"}');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('Page file [lid-worden.json]')
        ->assertFailed();

    expect(Page::query()->count())->toBe(0);
});

it('still creates the other pages when the strips of one do not fit, and fails', function (): void {
    File::put($this->content.'/pages/lid-worden.json', json_encode([
        'path' => '/lid-worden',
        'title' => 'Lid worden',
        'strips' => [['type' => 'teams', 'content' => []]],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('/lid-worden is niet aangemaakt')
        ->assertFailed();

    expect(Page::query()->pluck('path')->all())->toBe(['/']);
});

it('reports in English for an English site', function (): void {
    app()->setLocale('en');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('created with 2 strips')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Console/Commands/ImportPagesCommandTest.php`
Expected: FAIL with `The command "made-cms:pages" does not exist.`

- [ ] **Step 3: Write the implementation**

`src/Console/Commands/ImportPagesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Made\CmsPackage\Console\Commands;

use Illuminate\Console\Command;
use Made\CmsPackage\Content\Actions\ImportPage;
use Made\CmsPackage\Content\Actions\ListPageImports;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Exceptions\InvalidPageImport;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * Creates the pages of the page files that the site does not have yet. Safe on every deploy:
 * a page that exists is skipped, whatever editors did to it.
 */
final class ImportPagesCommand extends Command
{
    protected $signature = 'made-cms:pages';

    protected $description = 'Create the Made CMS pages of resources/cms/pages that do not exist yet';

    public function handle(): int
    {
        try {
            /** @var list<PageImport> $imports */
            $imports = ListPageImports::run();
        } catch (InvalidPageImport $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($imports === []) {
            $this->components->info($this->text('none', ['folder' => config()->string('made-cms-package.content.pages_path')]));

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($imports as $import) {
            try {
                $created = ImportPage::run($import);
            } catch (InvalidStripType|InvalidStripContent $exception) {
                $this->components->error($this->text('failed', ['path' => $import->path, 'reason' => $exception->getMessage()]));
                $failed = true;

                continue;
            }

            $this->components->twoColumnDetail($import->path, $created
                ? trans_choice('made-cms-package::console.pages.created', count($import->strips), ['count' => count($import->strips)])
                : $this->text('skipped'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::console.pages.'.$key, $replace);
    }
}
```

In `src/CmsPackageServiceProvider.php`:
- add `use Made\CmsPackage\Console\Commands\ImportPagesCommand;` below the `CreateEditorCommand` import;
- replace the command registration with

```php
        $this->commands([
            CreateEditorCommand::class,
            ImportPagesCommand::class,
        ]);
```

In `lang/nl/console.php`, add after the `editor` block:

```php
    'pages' => [
        'none' => 'Geen paginabestanden gevonden in :folder.',
        'created' => 'aangemaakt met :count strook|aangemaakt met :count stroken',
        'skipped' => 'bestaat al, overgeslagen',
        'failed' => ':path is niet aangemaakt: :reason',
    ],
```

In `lang/en/console.php`, add after the `editor` block:

```php
    'pages' => [
        'none' => 'No page files found in :folder.',
        'created' => 'created with :count strip|created with :count strips',
        'skipped' => 'already exists, skipped',
        'failed' => ':path was not created: :reason',
    ],
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/pest tests/Feature/Console/Commands/ImportPagesCommandTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Add the changelog line**

At the top of `### Added`:

```markdown
- `made-cms:pages` command: creates the pages of the page files that the site does not have yet, safe to run on every deploy, with Dutch and English output.
```

- [ ] **Step 6: Lint, run the full suite and commit**

Run: `composer lint && composer test`
Expected: all green; the console arch test confirms the command only goes through the Content actions.

```bash
git add src/Console/Commands/ImportPagesCommand.php src/CmsPackageServiceProvider.php lang/nl/console.php lang/en/console.php tests/Feature/Console/Commands/ImportPagesCommandTest.php CHANGELOG.md
git commit -m "feat: add made-cms:pages to create missing pages on deploy"
```

---

### Task 9: Documentation and conventions

**Files:**
- Modify: `README.md` (new section `## Strips`, package structure line)
- Modify: `CLAUDE.md` and `AGENTS.md` (identical files; append bullets to `## Architecture Guidelines`)
- Modify: `resources/boost/skills/made-cms-package-development/SKILL.md`

**Interfaces:**
- Consumes: everything above (documentation only).
- Produces: documentation that matches the public API of Tasks 1–8.

- [ ] **Step 1: Add the README section**

In `README.md`, insert this section directly before `## Roles and screens`:

````markdown
## Strips

A page is an ordered list of strips: a hero, a row of figures, an about-us section. The strip types of your site live in your application as JSON files, next to the components that render them, so a new field and the code that shows it ship in the same commit. The CMS reads them; editors only change content.

```
resources/cms/strips/hero.json   one file per strip type; the file name is its key, in kebab-case
resources/cms/pages/home.json    the initial content of a page
```

A strip type has a label and fields:

```json
{
    "$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json",
    "label": "Hero",
    "fields": [
        { "name": "title", "type": "text", "label": "Titel" },
        { "name": "intro", "type": "textarea", "label": "Introductie" },
        { "name": "photo", "type": "image", "label": "Foto" },
        { "name": "buttons", "type": "list", "label": "Knoppen", "fields": [
            { "name": "link", "type": "link", "label": "Knop" }
        ] }
    ]
}
```

The `$schema` line gives autocomplete and errors in PhpStorm and VS Code. Field names are written in snake_case. A field holds one of these:

| Type | Content |
|---|---|
| `text` | one line of text |
| `textarea` | several lines of text |
| `image` | `{"src": "/photos/hall.jpg", "alt": "The sports hall"}` |
| `link` | `{"label": "Become a member", "url": "/lid-worden"}` |
| `list` | a list of items, each with the fields of the list (one level deep) |

A page file holds the path, the title and the strips of a page with their content:

```json
{
    "$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/page.json",
    "path": "/",
    "title": "Home",
    "strips": [
        { "type": "hero", "content": { "title": "TTV Merwestad", "intro": "Since 1982 the place for table tennis." } }
    ]
}
```

Create the pages that do not exist yet. The command never touches a page that already exists, so it is safe to run on every deploy:

```bash
php artisan made-cms:pages
```

Read a page with its strips. A strip serialises to `{id, type, content}`, and its content holds every field of its type (empty text, `null` or `[]` when there is no value):

```php
use Made\CmsPackage\Content\Models\Page;

$page = Page::query()->published()->forPath($request->path())->firstOrFail();
```

Render one component per strip type, whatever your frontend is. With Blade:

```blade
@foreach ($page->strips as $strip)
    @include('strips.'.$strip->type, ['content' => $strip->content])
@endforeach
```

With Inertia, pass the strips as a prop (`Inertia::render('page', ['strips' => $page->strips])`) and map the type to a component, in React:

```tsx
const components = { hero: Hero, cijfers: Figures };

{strips.map((strip) => {
    const Strip = components[strip.type];

    return Strip ? <Strip key={strip.id} {...strip.content} /> : null;
})}
```

or in Vue:

```vue
<component v-for="strip in strips" :key="strip.id" :is="components[strip.type]" v-bind="strip.content" />
```

The folders move with the config keys `content.strips_path` and `content.pages_path`.
````

In the `## Package Structure` tree, replace

```
├── Content/    pages, blocks and publishing
```

with

```
├── Content/    pages, their strips, strip types and publishing
```

- [ ] **Step 2: Add the conventions**

Append these bullets to the end of the `## Architecture Guidelines` list (directly before `## Quick Commands`) in **both** `CLAUDE.md` and `AGENTS.md`:

```markdown
- A page is an ordered list of strips (`cms_strips`): `Page::query()->forPath()` finds it, `$page->strips` serialises to `{id, type, content}` for any frontend (Blade, Vue, React, Livewire). Never assume one frontend stack.
- Strip types are JSON files in the host (`content.strips_path`, the file name is the key): code, never database rows. Production only reads them (`ListStripTypes`); only `SaveStripType` writes them, and both go through `ParseStripType`.
- A new field type is a `FieldType` case, handled in `ParseStripType` and `ValidateStripContent`, and added to the enum in `resources/schema/strip.json`.
- Initial page content lives in `content.pages_path`; `ImportPage` creates a page only when its path does not exist, so content edited in the CMS is never overwritten.
```

- [ ] **Step 3: Update the Boost skill**

Use the `package-generate-skill` skill to update `resources/boost/skills/made-cms-package-development/SKILL.md`. It must end up with this step between `### 6. Use the actions` and `## Rules, References, and Templates`:

```markdown
### 7. Strips (page content)

- strip types are JSON files in `resources/cms/strips/<key>.json` (kebab-case key): a `label` and `fields` of the types `text`, `textarea`, `image`, `link` and `list`; start each file with `"$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json"`
- the initial content of a page goes in `resources/cms/pages/<page>.json` (`path`, `title`, `strips`); run `php artisan made-cms:pages` on every deploy, it only creates pages whose path does not exist yet
- read a page with `Page::query()->published()->forPath($request->path())->firstOrFail()` and pass `$page->strips` (`{id, type, content}`) to the frontend; render one component per `type`, in Blade, Vue, React or Livewire alike
- never store strip types in the database and never change them in production: they ship with the components that render them
```

- [ ] **Step 4: Run the full suite**

Run: `composer test`
Expected: all green (documentation only, but the suite guards against stray edits).

- [ ] **Step 5: Commit**

```bash
git add README.md CLAUDE.md AGENTS.md resources/boost/skills/made-cms-package-development/SKILL.md
git commit -m "docs: document strips, page files and made-cms:pages"
```

---

## Manual check after all tasks

1. **Prepare Testbench:**
   - Find the folder Testbench uses as `resource_path()`: `vendor/bin/testbench tinker --execute="dump(resource_path('cms'));"`.
   - Copy the example files there: `cp -R tests/Fixtures/content/. <that folder>/`. This puts both `strips/` (hero and cijfers) and `pages/` (home) in place.
2. **Import:** run `vendor/bin/testbench migrate:fresh` and then `vendor/bin/testbench made-cms:pages`.
   Expected: `/ … aangemaakt met 2 stroken`.
3. **Import again:** run `vendor/bin/testbench made-cms:pages`.
   Expected: `/ … bestaat al, overgeslagen`.
4. **Read the page back:** run `vendor/bin/testbench tinker --execute="dump(Made\CmsPackage\Content\Models\Page::query()->forPath('/')->sole()->strips->toArray());"`.
   Expected: two strips with `id`, `type` and `content`, where the hero content holds every field (`eyebrow`, `title`, `intro`, `photo` and `buttons`).
5. **Clean up:** remove the copied folder again (`rm -R <that folder>`); it is not part of the package.
