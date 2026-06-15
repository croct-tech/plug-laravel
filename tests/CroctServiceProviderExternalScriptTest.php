<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\CroctScript;
use Croct\Plug\Laravel\CroctServiceProvider;
use Croct\Plug\Laravel\Http\CroctMiddleware;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(CroctServiceProvider::class)]
#[TestDox('The Croct service provider with first-party serving disabled')]
final class CroctServiceProviderExternalScriptTest extends TestCase
{
    #[TestDox('Does not register the first-party route when the path is disabled.')]
    public function testDoesNotRegisterRoute(): void
    {
        $uris = \array_map(
            static fn ($route): string => $route->uri(),
            $this->app->make(Router::class)->getRoutes()->getRoutes(),
        );

        self::assertNotContains('_croct/plug.js', $uris);
    }

    #[TestDox('Injects the CDN script URL when first-party serving is disabled.')]
    public function testInjectsScriptUrl(): void
    {
        $this->get('/croct-test-page')
            ->assertSee(\sprintf('src="%s"', CroctScript::DEFAULT_SCRIPT_URL), false);
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
        // Valid credentials so the middleware can resolve the visitor token when the route is hit.
        $config->set('croct.app_id', '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a');
        $config->set('croct.api_key', '11111111-2222-4333-8444-555555555555');
        $config->set('croct.script.path', null);
    }

    /**
     * @param Router $router
     */
    protected function defineRoutes(mixed $router): void
    {
        $router->get('/croct-test-page', static fn (): string => '<html><head></head></html>')
            ->middleware(CroctMiddleware::class);
    }
}
