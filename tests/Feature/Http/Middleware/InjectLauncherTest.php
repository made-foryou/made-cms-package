<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Middleware\InjectLauncher;

function launcherTestPage(): string
{
    return '<!DOCTYPE html><html><head><title>Site</title></head><body><main>Welkom</main></body></html>';
}

beforeEach(function (): void {
    config(['made-cms-package.launcher.allowed_ips' => ['203.0.113.0/24']]);

    Route::middleware('web')->group(function (): void {
        Route::get('/', fn (): string => launcherTestPage());
        Route::post('/', fn (): string => launcherTestPage());
        Route::get('json', fn (): array => ['body' => '</body>']);
        Route::get('redirect', fn () => redirect('/'));
        Route::get('missing', fn () => response(launcherTestPage(), 404));
        Route::get('fragment', fn (): string => '<p>Geen body</p>');
        Route::get('text', fn () => response('</body>', 200, ['Content-Type' => 'text/plain']));
        Route::get('download', fn () => response(launcherTestPage(), 200, ['Content-Disposition' => 'attachment; filename="page.html"']));
        Route::get('nested', fn (): string => '<html><body><template><body></body></template><p>Tekst</p></BODY></html>');
        Route::get('nova/resources', fn (): string => launcherTestPage());
        Route::get('view', fn () => view('made-cms-package-test::page'));
        Route::get('cached', fn () => response(launcherTestPage())->setPublic()->setSharedMaxAge(600)->setEtag('abc'));
        Route::get('sized', fn () => response(launcherTestPage(), 200, ['Content-Length' => (string) strlen(launcherTestPage())]));
        Route::get('gzipped', fn () => response(launcherTestPage(), 200, ['Content-Encoding' => 'gzip']));
        Route::get('streamed', fn () => response()->stream(function (): void {}, 200, ['Content-Type' => 'text/html']));
        Route::get('file', fn () => response()->file(__DIR__.'/../../../Fixtures/views/page.blade.php', ['Content-Type' => 'text/html']));
        Route::get('cms/page', fn (): string => launcherTestPage())->name('made-cms-package.test-page');
        Route::get('titled', fn (): string => '<html><head><title>Over ons | TTV Merwestad</title></head><body></body></html>');
        Route::get('named', fn (): string => '<html><head><title>Iets anders</title><meta name="made-cms:page" content="Trainingstijden"></head><body></body></html>');
        Route::get('over-ons', fn (): string => '<html><head></head><body></body></html>');
    });

    app('view')->addNamespace('made-cms-package-test', __DIR__.'/../../../Fixtures/views');
});

function visitLauncherPageFrom(string $ip): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->get('/');
}

it('injects the launcher for a visitor from an allowed ip address', function (): void {
    $response = visitLauncherPageFrom('203.0.113.5');

    $response->assertOk()
        ->assertSee('<made-cms-launcher', false)
        ->assertSee('<main>Welkom</main>', false);

    expect(substr_count((string) $response->getContent(), '<made-cms-launcher'))->toBe(1)
        ->and((string) $response->getContent())->toEndWith('</body></html>');
});

it('injects the launcher for a remembered editor from any ip address', function (): void {
    $this->withCookie(InjectLauncher::COOKIE, '1')
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->get('/')
        ->assertSee('<made-cms-launcher', false);
});

it('does not trust a cookie that was not encrypted by the application', function (): void {
    $this->withUnencryptedCookie(InjectLauncher::COOKIE, '1')
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->get('/')
        ->assertDontSee('<made-cms-launcher', false);
});

it('does not inject the launcher for an unknown visitor', function (): void {
    visitLauncherPageFrom('198.51.100.20')->assertOk()->assertDontSee('<made-cms-launcher', false);
});

it('does not inject the launcher when it is disabled', function (): void {
    config(['made-cms-package.launcher.enabled' => false]);

    visitLauncherPageFrom('203.0.113.5')->assertDontSee('<made-cms-launcher', false);
});

it('only injects into successful full html page responses', function (string $method, string $uri, array $headers = []): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->call($method, $uri, server: collect($headers)->mapWithKeys(
            fn (string $value, string $name): array => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value],
        )->all());

    expect((string) $response->getContent())->not->toContain('<made-cms-launcher');
})->with([
    'post request' => ['POST', '/'],
    'inertia visit' => ['GET', '/', ['X-Inertia' => 'true']],
    'ajax request' => ['GET', '/', ['X-Requested-With' => 'XMLHttpRequest']],
    'json response' => ['GET', 'json'],
    'redirect' => ['GET', 'redirect'],
    'error page' => ['GET', 'missing'],
    'html without a body' => ['GET', 'fragment'],
    'plain text' => ['GET', 'text'],
    'download' => ['GET', 'download'],
    'excluded path' => ['GET', 'nova/resources'],
    'cms route' => ['GET', 'cms/page'],
    'encoded body' => ['GET', 'gzipped'],
    'streamed response' => ['GET', 'streamed'],
    'file response' => ['GET', 'file'],
]);

it('updates the content length after the injection', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('sized');

    $response->assertSee('<made-cms-launcher', false);

    expect($response->headers->get('Content-Length'))->toBe((string) strlen((string) $response->getContent()));
});

it('injects before the last closing body tag', function (): void {
    $content = (string) $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('nested')->getContent();

    expect($content)->toStartWith('<html><body><template><body></body></template><p>Tekst</p><made-cms-launcher')
        ->toEndWith('</BODY></html>');
});

it('keeps the original view for assertions in host tests', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('view');

    $response->assertSee('<made-cms-launcher', false)
        ->assertViewIs('made-cms-package-test::page');

    expect($response->original)->toBeInstanceOf(View::class);
});

it('keeps the injected page out of shared caches', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('cached');

    $response->assertSee('<made-cms-launcher', false);

    expect($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('public'))->toBeFalse()
        ->and($response->headers->hasCacheControlDirective('s-maxage'))->toBeFalse()
        ->and($response->headers->has('ETag'))->toBeFalse();
});

it('leaves pages without the launcher untouched', function (): void {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->get('cached');

    expect($response->headers->hasCacheControlDirective('public'))->toBeTrue()
        ->and($response->headers->get('ETag'))->toBe('"abc"');
});

it('renders the launcher texts in the configured locale', function (): void {
    visitLauncherPageFrom('203.0.113.5')->assertSee('Beheer deze site');

    config(['made-cms-package.launcher.locale' => 'en']);

    visitLauncherPageFrom('203.0.113.5')->assertSee('Manage this site');
});

it('falls back to English for a locale the package does not ship', function (): void {
    config(['made-cms-package.launcher.locale' => 'de', 'app.fallback_locale' => 'de']);

    visitLauncherPageFrom('203.0.113.5')
        ->assertSee('lang="en"', false)
        ->assertSee('Manage this site')
        ->assertDontSee('made-cms-package::launcher');
});

it('names the site host from the application url', function (): void {
    config(['app.url' => 'https://www.ttvmerwestad.nl']);

    visitLauncherPageFrom('203.0.113.5')->assertSee('ttvmerwestad.nl')->assertDontSee('www.ttvmerwestad.nl');
});

it('shows the application name in the panel header', function (): void {
    config(['app.name' => 'TTV Merwestad', 'app.url' => 'https://ttvmerwestad.nl']);

    visitLauncherPageFrom('203.0.113.5')->assertSee('<span class="sitelabel">TTV Merwestad</span>', false);
});

it('names the application in the introduction of the panel', function (): void {
    config(['app.name' => 'TTV Merwestad', 'app.url' => 'https://ttvmerwestad.nl']);

    visitLauncherPageFrom('203.0.113.5')
        ->assertSee('Je gaat TTV Merwestad beheren.')
        ->assertDontSee('Je gaat ttvmerwestad.nl beheren.');
});

it('falls back to the site host when the application has no name of its own', function (?string $name): void {
    config(['app.name' => $name, 'app.url' => 'https://ttvmerwestad.nl']);

    visitLauncherPageFrom('203.0.113.5')->assertSee('<span class="sitelabel">ttvmerwestad.nl</span>', false);
})->with([
    'laravel default' => 'Laravel',
    'empty' => '',
    'missing' => null,
]);

it('links the launcher assets relative to the current host', function (): void {
    visitLauncherPageFrom('203.0.113.5')
        ->assertSee('href="/cms/launcher/launcher.css?v=', false)
        ->assertSee('src="/cms/launcher/launcher.js?v=', false);
});

it('adds the csp nonce to the launcher stylesheet and script', function (): void {
    Vite::useCspNonce('made-test-nonce');

    $content = (string) visitLauncherPageFrom('203.0.113.5')->getContent();

    expect($content)->toMatch('/<link rel="stylesheet" href="[^"]+" nonce="made-test-nonce">/')
        ->toMatch('/<script type="module" src="[^"]+" nonce="made-test-nonce"><\/script>/');
});

it('never breaks the host page when the launcher fails', function (): void {
    config(['made-cms-package.launcher.allowed_ips' => 'not-a-list']);

    visitLauncherPageFrom('203.0.113.5')->assertOk()->assertSee('<main>Welkom</main>', false)->assertDontSee('<made-cms-launcher', false);
});

it('shows the edit bar instead of the tile to a logged-in editor from any ip address', function (): void {
    $editor = Editor::factory()->create(['name' => 'Menno Tempelaar']);

    $this->actingAs($editor, 'made-cms')
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->get('/')
        ->assertSee('data-view="edit_bar"', false)
        ->assertSee('<span class="avatar" aria-hidden="true">M</span>', false);
});

it('shows the tile to a visitor who is not logged in', function (): void {
    visitLauncherPageFrom('203.0.113.5')->assertSee('data-view="tile"', false);
});

it('names the current page in the edit bar', function (string $uri, string $page): void {
    config(['app.name' => 'TTV Merwestad']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get($uri)
        ->assertSee('<span class="balk-pagina">'.$page.'</span>', false);
})->with([
    'the page meta tag' => ['named', 'Trainingstijden'],
    'the title without the site name' => ['titled', 'Over ons'],
    'the home page' => ['/', 'Site'],
    'the path' => ['over-ons', 'Over ons'],
]);
