<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel;

use Croct\Plug\IdentityResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the user identity from the Laravel authentication guard.
 */
final class LaravelIdentityResolver implements IdentityResolver
{
    private AuthFactory $auth;

    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    public function getUserId(): ?string
    {
        $id = $this->auth->guard()->id();

        return $id === null ? null : (string) $id;
    }
}
