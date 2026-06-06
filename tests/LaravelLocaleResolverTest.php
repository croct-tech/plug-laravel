<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\Laravel\LaravelLocaleResolver;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelLocaleResolver::class)]
#[TestDox('The Laravel locale resolver')]
final class LaravelLocaleResolverTest extends TestCase
{
    #[TestDox('Returns the active application locale.')]
    public function testReturnsApplicationLocale(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('getLocale')->willReturn('fr');

        self::assertSame('fr', (new LaravelLocaleResolver($app))->getLocale());
    }
}
