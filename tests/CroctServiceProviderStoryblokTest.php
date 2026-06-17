<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\Laravel\CroctServiceProvider;
use Croct\Plug\Laravel\Tests\Fixtures\FakeCroctStoriesApi;
use Croct\Plug\Laravel\Tests\Fixtures\FakeStoriesApi;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(CroctServiceProvider::class)]
#[TestDox('The Croct service provider Storyblok integration')]
final class CroctServiceProviderStoryblokTest extends TestCase
{
    private const STORIES_API = 'Storyblok\\Api\\StoriesApiInterface';

    private const DECORATOR = 'Croct\\Plug\\Storyblok\\CroctStoriesApi';

    #[TestDox('Enables the Storyblok integration by default.')]
    public function testEnabledByDefault(): void
    {
        self::assertTrue($this->app->make('config')->get('croct.storyblok.enabled'));
    }

    #[DefineEnvironment('bindAndSimulateDecorator')]
    #[RunInSeparateProcess]
    #[TestDox('Decorates a bound Storyblok Stories API with the Croct decorator.')]
    public function testDecoratesStoriesApi(): void
    {
        $stories = $this->app->make(self::STORIES_API);

        self::assertInstanceOf(FakeCroctStoriesApi::class, $stories);
        // The original binding is wrapped, not replaced.
        self::assertInstanceOf(FakeStoriesApi::class, $stories->inner);
    }

    #[DefineEnvironment('bindStoriesApiWithIntegrationDisabled')]
    #[TestDox('Leaves the Stories API untouched when the integration is disabled.')]
    public function testDoesNotDecorateWhenDisabled(): void
    {
        self::assertInstanceOf(FakeStoriesApi::class, $this->app->make(self::STORIES_API));
    }

    #[TestDox('Does nothing when no Storyblok Stories API is bound.')]
    public function testDoesNotBindWhenStoriesApiAbsent(): void
    {
        self::assertFalse($this->app->bound(self::STORIES_API));
    }

    protected function bindAndSimulateDecorator(mixed $app): void
    {
        // Simulate croct/plug-storyblok being installed so the decorator class is discoverable.
        if (!\class_exists(self::DECORATOR)) {
            \class_alias(FakeCroctStoriesApi::class, self::DECORATOR);
        }

        $this->withValidCredentials($app);

        $app->instance(self::STORIES_API, new FakeStoriesApi());
    }

    protected function bindStoriesApiWithIntegrationDisabled(mixed $app): void
    {
        $app->make('config')->set('croct.storyblok.enabled', false);

        $app->instance(self::STORIES_API, new FakeStoriesApi());
    }

    private function withValidCredentials(mixed $app): void
    {
        // Resolving the decorated Stories API builds a Plug, which needs valid credentials.
        $config = $app->make('config');
        $config->set('croct.app_id', '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a');
        $config->set('croct.api_key', '11111111-2222-4333-8444-555555555555');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [CroctServiceProvider::class];
    }
}
