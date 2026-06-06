<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\IdentityResolver;
use Croct\Plug\Laravel\CroctManager;
use Croct\Plug\Laravel\CroctServiceProvider;
use Croct\Plug\Laravel\Http\CroctMiddleware;
use Croct\Plug\Laravel\LaravelIdentityResolver;
use Croct\Plug\Laravel\LaravelLocaleResolver;
use Croct\Plug\LocaleResolver;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionProperty;

#[CoversClass(CroctServiceProvider::class)]
#[TestDox('The Croct service provider')]
final class CroctServiceProviderTest extends TestCase
{
    #[TestDox('Binds the framework adapters.')]
    public function testBindsAdapters(): void
    {
        self::assertInstanceOf(LaravelIdentityResolver::class, $this->app->make(IdentityResolver::class));
        self::assertInstanceOf(LaravelLocaleResolver::class, $this->app->make(LocaleResolver::class));
    }

    #[TestDox('Binds the request-scoped manager and the middleware.')]
    public function testBindsManagerAndMiddleware(): void
    {
        self::assertInstanceOf(CroctManager::class, $this->app->make(CroctManager::class));
        self::assertInstanceOf(CroctMiddleware::class, $this->app->make(CroctMiddleware::class));
    }

    #[TestDox('Merges the package configuration.')]
    public function testMergesConfiguration(): void
    {
        self::assertSame('none', $this->app->make('config')->get('croct.cookie.same_site'));
        self::assertTrue($this->app->make('config')->get('croct.script.auto_inject'));
    }

    #[TestDox('Appends the middleware to the web group automatically.')]
    public function testAppendsMiddlewareToWebGroup(): void
    {
        $web = $this->app->make(Router::class)->getMiddlewareGroups()['web'] ?? [];

        self::assertIsArray($web);
        self::assertContains(CroctMiddleware::class, $web);
    }

    #[TestDox('Excludes the session cookies from Laravel cookie encryption.')]
    public function testExcludesSessionCookiesFromEncryption(): void
    {
        $except = (new ReflectionProperty(EncryptCookies::class, 'neverEncrypt'))->getValue();

        self::assertIsArray($except);
        self::assertContains('ct.client_id', $except);
        self::assertContains('ct.user_token', $except);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [CroctServiceProvider::class];
    }

    protected function defineEnvironment(mixed $app): void
    {
        $config = $app->make('config');
        // app_id left null exercises the empty-string fallback; domain a string exercises the other.
        $config->set('croct.app_id', null);
        $config->set('croct.api_key', 'app-key');
        $config->set('croct.cookie.domain', 'example.com');
    }
}
