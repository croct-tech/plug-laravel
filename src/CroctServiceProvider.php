<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel;

use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Croct;
use Croct\Plug\CroctScriptProvider;
use Croct\Plug\IdentityResolver;
use Croct\Plug\Laravel\Http\CroctMiddleware;
use Croct\Plug\Laravel\Http\CroctScriptController;
use Croct\Plug\LoadMode;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Plug;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Psr\Log\LoggerInterface;

/**
 * Wires Croct into Laravel with zero application code.
 *
 * It binds the request-scoped manager and the framework adapters, then appends the middleware to the
 * web group so every web response is bootstrapped and personalized automatically. The session
 * cookies are excluded from Laravel's cookie encryption because the client SDK reads them.
 */
final class CroctServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/croct.php', 'croct');

        $this->app->singleton(
            IdentityResolver::class,
            static function (Application $app): IdentityResolver {
                return new LaravelIdentityResolver($app->make(AuthFactory::class));
            },
        );

        $this->app->singleton(
            LocaleResolver::class,
            static fn (Application $app): LocaleResolver => new LaravelLocaleResolver($app),
        );

        $this->app->scoped(CroctManager::class, static function (Application $app): CroctManager {
            $config = $app->make(Config::class);

            return new CroctManager(
                $app->make(Request::class),
                $app->make(LocaleResolver::class),
                self::getString($config->get('croct.app_id')),
                self::getString($config->get('croct.api_key')),
                self::getOptionalString($config->get('croct.base_endpoint_url')),
                (bool) $config->get('croct.locale.enabled'),
                self::getOptionalString($config->get('croct.locale.default')),
                self::getOptionalString($config->get('croct.cookie.domain')),
                (bool) $config->get('croct.cookie.secure'),
                self::getString($config->get('croct.cookie.same_site')),
                $app->bound(ContentProvider::class) ? $app->make(ContentProvider::class) : null,
                $app->make(LoggerInterface::class),
                self::getInt($config->get('croct.token_duration'), Croct::DEFAULT_TOKEN_DURATION),
                $app->make(IdentityResolver::class),
            );
        });

        $this->app->bind(
            Plug::class,
            static fn (Application $app): Plug => $app->make(CroctManager::class)->getPlug(),
        );

        $this->app->bind(CroctScriptProvider::class, static function (Application $app): CroctScriptProvider {
            $config = $app->make(Config::class);

            return new CroctScriptProvider(
                // Disable transparent decompression so the upstream content encoding is relayed as-is.
                new Client(['decode_content' => false]),
                new HttpFactory(),
                $app->make(Cache::class),
                self::getString($config->get('croct.script.script_url')),
            );
        });

        $this->app->bind(CroctMiddleware::class, static function (Application $app): CroctMiddleware {
            $config = $app->make(Config::class);

            return new CroctMiddleware(
                $app->make(CroctManager::class),
                (bool) $config->get('croct.script.auto_inject'),
                self::resolveScriptSrc($config),
                self::getString($config->get('croct.script.placement')),
                LoadMode::tryFrom(self::getString($config->get('croct.script.mode'))) ?? LoadMode::DEFER,
            );
        });
    }

    public function boot(): void
    {
        $this->publishes(
            [__DIR__ . '/../config/croct.php' => $this->app->configPath('croct.php')],
            'croct-config',
        );

        // The client SDK reads these cookies, so Laravel must never encrypt them.
        EncryptCookies::except(['ct_client_id', 'ct_user_token']);

        // The croct Blade directives are an optional integration, registered once Blade resolves.
        $this->callAfterResolving('blade.compiler', self::registerBladeDirectives(...));

        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('web', CroctMiddleware::class);

        // Serve the SDK first-party so the browser loads it from the application's own origin.
        $path = self::getOptionalString($this->app->make(Config::class)->get('croct.script.path'));

        if ($path !== null) {
            $router->get($path, CroctScriptController::class);
        }
    }

    /**
     * Registers the Blade directives for running snippets once the SDK is plugged.
     *
     * The croct/endcroct pair wraps a snippet in the onCroctPlug queue, taking an optional nonce
     * expression for CSP.
     */
    private static function registerBladeDirectives(BladeCompiler $blade): void
    {
        $blade->directive('croct', static function (string $expression): string {
            $nonce = $expression === '' ? 'null' : $expression;

            return '<?php $__croctNonce = ' . $nonce . '; \ob_start(); ?>';
        });

        $blade->directive('endcroct', static function (): string {
            return '<?php echo new \Croct\Plug\CroctCallback((string) \ob_get_clean(), $__croctNonce); ?>';
        });
    }

    private static function resolveScriptSrc(Config $config): string
    {
        $path = self::getOptionalString($config->get('croct.script.path'));

        return $path ?? self::getString($config->get('croct.script.script_url'));
    }

    private static function getString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function getOptionalString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function getInt(mixed $value, int $default): int
    {
        return \is_numeric($value) ? (int) $value : $default;
    }
}
