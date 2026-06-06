<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\Laravel\LaravelIdentityResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelIdentityResolver::class)]
#[TestDox('The Laravel identity resolver')]
final class LaravelIdentityResolverTest extends TestCase
{
    #[TestDox('Returns the identifier of the authenticated user.')]
    public function testReturnsUserId(): void
    {
        self::assertSame('42', $this->createResolver(42)->getUserId());
    }

    #[TestDox('Returns null when the visitor is a guest.')]
    public function testReturnsNullWhenGuest(): void
    {
        self::assertNull($this->createResolver(null)->getUserId());
    }

    private function createResolver(int|string|null $id): LaravelIdentityResolver
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('id')->willReturn($id);

        $auth = $this->createMock(AuthFactory::class);
        $auth->method('guard')->willReturn($guard);

        return new LaravelIdentityResolver($auth);
    }
}
