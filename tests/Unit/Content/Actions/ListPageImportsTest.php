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

it('reads a page file with a schema key', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"$schema": "../../../vendor/made-foryou/made-cms-package/resources/schema/page.json", "path": "/lid-worden", "title": "Lid worden", "strips": []}');

    expect(array_map(fn (PageImport $page): string => $page->path, ListPageImports::run()))->toContain('/lid-worden');
});
