<?php

declare(strict_types=1);

namespace Made\CmsPackage;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Made\CmsPackage\Access\Actions\RecogniseDevice;
use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Listeners\SendLoginCode;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Console\Commands\CreateEditorCommand;
use Made\CmsPackage\Console\Commands\MediaThumbnailsCommand;
use Made\CmsPackage\Console\Commands\MediaUsageCommand;
use Made\CmsPackage\Console\Commands\MediaVariantsCommand;
use Made\CmsPackage\Console\Commands\PrunePagesCommand;
use Made\CmsPackage\Console\Commands\SitemapCommand;
use Made\CmsPackage\Content\Attention\EmptyPageSource;
use Made\CmsPackage\Content\Attention\MetaDescriptionSource;
use Made\CmsPackage\Content\Attention\MetaTitleSource;
use Made\CmsPackage\Content\Attention\StaleDraftSource;
use Made\CmsPackage\Content\Data\AttentionSources;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Data\ServedAddress;
use Made\CmsPackage\Content\Data\SitemapSources;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Events\PageDeleted;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Events\PageRestored;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;
use Made\CmsPackage\Content\Listeners\RebuildSitemaps;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Sitemap\PageSitemapSource;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Middleware\InjectLauncher;
use Made\CmsPackage\Http\Responses\ThrottledResponse;
use Made\CmsPackage\Http\Routing\RouterMixin;
use Made\CmsPackage\Http\Routing\SiteRoutes;
use Made\CmsPackage\Http\Session\LoginSession;
use Made\CmsPackage\Support\PublishedMigrations;
use Symfony\Component\HttpFoundation\IpUtils;

class CmsPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/made-cms-package.php', 'made-cms-package');

        $this->registerGuard();

        $this->registerStripTypes();

        $this->registerPageRequestState();

        $this->registerSitemap();
        $this->registerAttention();

        // Route::madeCms(): the site serves the CMS pages with one line in its own routes. Registered here and not in
        // boot(), because the routes of a site may load as soon as its route provider boots, before this provider does.
        Router::mixin(new RouterMixin);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/made-cms-package.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'made-cms-package');

        // The launcher markup belongs to its package-served script and styles, so it is never published.
        $this->loadViewsFrom(__DIR__.'/../resources/launcher', 'made-cms-package-launcher');

        // <x-made-cms::strips>: the Blade components a site renders its CMS pages with.
        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade): void {
            $blade->componentNamespace('Made\\CmsPackage\\Http\\Components', 'made-cms');
        });

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'made-cms-package');

        // Synchronous on purpose: the event carries the plain code, which must never end up in a queue payload.
        Event::listen(LoginCodeIssued::class, SendLoginCode::class);

        // Every change of a page that can change the sitemap builds it again, once the answer is sent.
        Event::listen([
            PagePublished::class,
            PageUpdated::class,
            PageMoved::class,
            PageDeleted::class,
            PageRestored::class,
            PageVisibilityChanged::class,
            PageMetaUpdated::class,
        ], RebuildSitemaps::class);

        $this->registerRateLimiters();

        // A short alias instead of the class name in cms_routes, so moving the model never breaks an address.
        // Added to the host's map; requiring a map for every model is the host's call.
        Relation::morphMap(['cms_page' => Page::class]);

        // Unconditional, because the kernel only resolves for HTTP requests. Appending to the kernel group
        // (instead of the router) survives every later sync of the middleware groups to the router.
        $this->callAfterResolving(HttpKernel::class, function (mixed $kernel): void {
            if ($kernel instanceof Kernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
                $kernel->appendMiddlewareToGroup('web', InjectLauncher::class);
            }

            // The sitemap is built after the answer (defer()). A kernel of its own, or global middleware the site
            // replaced, may lack the middleware that runs deferred work; without it the sitemap would never follow.
            if ($kernel instanceof Kernel && ! $kernel->hasMiddleware(InvokeDeferredCallbacks::class)) {
                $kernel->prependMiddleware(InvokeDeferredCallbacks::class);
            }
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CreateEditorCommand::class,
            PrunePagesCommand::class,
            SitemapCommand::class,
            MediaUsageCommand::class,
            MediaThumbnailsCommand::class,
            MediaVariantsCommand::class,
        ]);

        // Deleted pages can be restored for a while; after that they go for good, with the host's scheduler.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('made-cms:prune')->daily();
        });

        $this->publishes([
            __DIR__.'/../config/made-cms-package.php' => config_path('made-cms-package.php'),
        ], ['made-cms-package', 'made-cms-package-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/made-cms-package'),
        ], ['made-cms-package', 'made-cms-package-assets']);

        // Each migration on its own: one the host published already is skipped, never published again under a new
        // date (PublishedMigrations). Only a command reads the folder of the host.
        if ($this->app->runningInConsole()) {
            $this->publishes(
                PublishedMigrations::paths(__DIR__.'/../database/migrations', database_path('migrations'), now()),
                ['made-cms-package', 'made-cms-package-migrations'],
            );
        }
    }

    /**
     * Editors log in through a session guard of their own, so the CMS never touches the users or guards of the host.
     * A host that defines the guard or provider itself keeps its own definition.
     */
    private function registerGuard(): void
    {
        $config = $this->app->make('config');
        $guard = $config->string('made-cms-package.auth.guard');

        if (! $config->has("auth.providers.{$guard}")) {
            $config->set("auth.providers.{$guard}", ['driver' => 'eloquent', 'model' => Editor::class]);
        }

        if (! $config->has("auth.guards.{$guard}")) {
            $config->set("auth.guards.{$guard}", ['driver' => 'session', 'provider' => $guard]);
        }
    }

    /**
     * Off for every request, until the page route switches it on for an editor, and no page served yet, until the page
     * route serves one. Scoped, so a long-running worker (Octane, queue) forgets them with the other per-request instances.
     */
    private function registerPageRequestState(): void
    {
        $this->app->scoped(EditMode::class, static fn (): EditMode => EditMode::off());
        $this->app->scoped(ServedAddress::class, static fn (): ServedAddress => ServedAddress::none());
    }

    /**
     * What the sitemap lists, in this order: the pages (collections and images add a source of their own later), and
     * the route groups that serve them, one sitemap each.
     */
    private function registerSitemap(): void
    {
        $this->app->bind(SitemapSources::class, static fn (): SitemapSources => new SitemapSources([new PageSitemapSource]));

        // Read from the routes each time it is needed, never at boot: the routes of the site load after this provider.
        $this->app->bind(SitePrefixes::class, static fn (Application $app): SitePrefixes => SiteRoutes::prefixes($app->make(Router::class)));
    }

    /**
     * Where the attention points of a page come from, in this order (links and media add a source of their own later).
     */
    private function registerAttention(): void
    {
        $this->app->bind(AttentionSources::class, static fn (): AttentionSources => new AttentionSources([
            new StaleDraftSource,
            new MetaDescriptionSource,
            new MetaTitleSource,
            new EmptyPageSource,
        ]));
    }

    /**
     * The catalogue of the site's strip types. It scans the folder only once, the first time it is needed.
     */
    private function registerStripTypes(): void
    {
        $this->app->singleton(StripTypes::class, function (): StripTypes {
            $config = $this->app->make('config');

            return StripTypes::discover(
                $config->string('made-cms-package.strips.path'),
                $config->string('made-cms-package.strips.namespace'),
            );
        });
    }

    /**
     * Every limit is keyed on what was typed (hashed) and the network, never on whether an account exists,
     * so a limit answers the same for known and unknown addresses. Needs a persistent cache store.
     */
    private function registerRateLimiters(): void
    {
        $throttled = fn (Request $request, array $headers): mixed => ThrottledResponse::make($headers);
        $email = fn (Request $request): string => hash('sha256', (string) LoginSession::of($request)->email());
        $network = $this->network(...);

        // Per typed address, whatever the network, so rotating ip addresses gets no further. A browser that logged in
        // with the address before counts on its own, so whoever exhausts the limit never locks the editor out there.
        $address = fn (Request $request, Limit $limit, string $name): array => ($key = $this->address($request)) === null
            ? []
            : [$limit->by($name.'|'.$key)->response($throttled)];

        RateLimiter::for('made-cms-package.login-code', fn (Request $request): array => [
            Limit::perMinutes(10, 3)->by('login-code|'.$email($request).'|'.$network($request))->response($throttled),
            Limit::perHour(20)->by('login-code-ip|'.$network($request))->response($throttled),
            ...$address($request, Limit::perHour(10), 'login-code-address'),
        ]);

        // Checking a code or a link: every code allows login_code.max_attempts tries, and asking for codes is limited.
        RateLimiter::for('made-cms-package.login', fn (Request $request): array => [
            Limit::perMinute(10)->by('login|'.$email($request).'|'.$network($request))->response($throttled),
            Limit::perMinute(30)->by('login-ip|'.$network($request))->response($throttled),
        ]);

        RateLimiter::for('made-cms-package.password', fn (Request $request): array => [
            Limit::perMinute(10)->by('password|'.$email($request).'|'.$network($request))->response($throttled),
            Limit::perMinute(30)->by('password-ip|'.$network($request))->response($throttled),
            ...$address($request, Limit::perHour(50), 'password-address'),
        ]);

        // Per editor: the one who is logged in, or the one halfway through a password login. Reaching this step takes
        // the right password, so a limit per editor, whatever the network, tells nothing about an account. Every
        // request counts, but a right code ends the step: in practice it counts the wrong codes.
        RateLimiter::for('made-cms-package.two-factor', function (Request $request) use ($throttled, $network): array {
            $editor = $this->twoFactorEditor($request);

            return $editor === null
                ? [Limit::perMinute(5)->by('two-factor|guest|'.$network($request))->response($throttled)]
                : [
                    Limit::perMinute(5)->by('two-factor|'.$editor.'|'.$network($request))->response($throttled),
                    Limit::perHour(10)->by('two-factor-editor|'.$editor)->response($throttled),
                ];
        });
    }

    /**
     * The editor who enters a second factor: the one who is logged in, or the one halfway through a password login.
     */
    private function twoFactorEditor(Request $request): ?string
    {
        $editor = $request->user(config()->string('made-cms-package.auth.guard'))?->getAuthIdentifier()
            ?? LoginSession::of($request)->pendingTwoFactor()['editor']
            ?? null;

        return is_scalar($editor) ? (string) $editor : null;
    }

    /**
     * The typed address (hashed) of the login in this browser, marked when the browser logged in with it before;
     * null when no address was typed yet.
     */
    private function address(Request $request): ?string
    {
        $email = LoginSession::of($request)->email();

        if ($email === null) {
            return null;
        }

        $known = RecogniseDevice::run(new LoginStart($email, EditorDevice::read($request)));

        return hash('sha256', $email).($known ? '|device' : '');
    }

    /**
     * The network a request comes from: an IPv4 address as it is (also written as IPv6), an IPv6 address as its /64,
     * because one connection usually gets a whole /64 to pick addresses from.
     */
    private function network(Request $request): string
    {
        $ip = $request->ip();

        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'unknown';
        }

        return IpUtils::anonymize($ip, 0, 8);
    }
}
