<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel;

use Croct\Plug\LocaleResolver;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves the locale from the Laravel application locale.
 *
 * This follows the application's active locale (config, middleware, or a manual setLocale) rather
 * than the raw Accept-Language header, so Croct personalizes for the language the app is rendering.
 */
final class LaravelLocaleResolver implements LocaleResolver
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getLocale(): string
    {
        return $this->app->getLocale();
    }
}
