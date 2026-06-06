<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel;

use Croct\Plug\Cookie;
use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Croct;
use Croct\Plug\IdentityResolver;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Plug;
use Croct\Plug\RequestContext;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use Illuminate\Http\Request;

/**
 * Builds a request-scoped {@see Plug} from the current Laravel request.
 *
 * It mirrors the role of the Symfony factory: it reads the visitor cookies, builds the Plug with the
 * request context and resolved locale, and tracks whether the request used visitor-specific data so
 * the middleware can mark the response private.
 */
final class CroctManager
{
    private Request $request;

    private IdentityResolver $identity;

    private LocaleResolver $locale;

    private string $appId;

    private string $apiKey;

    private ?string $baseEndpointUrl;

    private bool $localeEnabled;

    private ?string $defaultLocale;

    private ?string $cookieDomain;

    private bool $cookieSecure;

    private string $cookieSameSite;

    private ?Plug $plug = null;

    private ?CookieStorage $storage = null;

    private bool $personalized = false;

    public function __construct(
        Request $request,
        IdentityResolver $identity,
        LocaleResolver $locale,
        string $appId,
        string $apiKey,
        ?string $baseEndpointUrl = null,
        bool $localeEnabled = true,
        ?string $defaultLocale = null,
        ?string $cookieDomain = null,
        bool $cookieSecure = true,
        string $cookieSameSite = 'none',
    ) {
        $this->request = $request;
        $this->identity = $identity;
        $this->locale = $locale;
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->baseEndpointUrl = $baseEndpointUrl;
        $this->localeEnabled = $localeEnabled;
        $this->defaultLocale = $defaultLocale;
        $this->cookieDomain = $cookieDomain;
        $this->cookieSecure = $cookieSecure;
        $this->cookieSameSite = $cookieSameSite;
    }

    public function getPlug(): Plug
    {
        return $this->plug ??= $this->createPlug();
    }

    public function isPersonalized(): bool
    {
        return $this->personalized;
    }

    /**
     * @return list<Cookie>
     */
    public function getResponseCookies(): array
    {
        return $this->getStorage()->getResponseCookies();
    }

    /**
     * Returns the visitor-independent options for bootstrapping the client-side SDK.
     *
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array
    {
        return [
            'appId' => $this->appId,
            'disableCidMirroring' => true,
            'cookie' => $this->createCookieConfiguration()->toBrowserCookies(),
        ];
    }

    /**
     * Keeps the visitor token in sync with the authenticated user, re-identifying on login and
     * anonymizing on logout, only when the user no longer matches the stored token.
     */
    public function syncIdentity(): void
    {
        $userId = $this->identity->getUserId();
        $token = $this->getStorage()->getUserToken();

        $matches = $userId === null
            ? ($token?->isAnonymous() ?? true)
            : ($token?->isSubject($userId) ?? false);

        if ($matches) {
            return;
        }

        $plug = $this->getPlug();

        if ($userId === null) {
            $plug->anonymize();

            return;
        }

        $plug->identify($userId);
    }

    private function getStorage(): CookieStorage
    {
        return $this->storage ??= CookieStorage::fromArray(
            $this->request->cookies->all(),
            $this->createCookieConfiguration(),
        );
    }

    private function createCookieConfiguration(): CookieConfiguration
    {
        return new CookieConfiguration(
            domain: $this->cookieDomain,
            secure: $this->cookieSecure,
            sameSite: \ucfirst($this->cookieSameSite),
        );
    }

    private function createPlug(): Plug
    {
        $context = new RequestContext(
            url: $this->request->getUri(),
            referrer: $this->request->headers->get('referer'),
            clientAgent: $this->request->headers->get('User-Agent'),
            clientIp: $this->request->getClientIp(),
            preferredLocale: $this->resolveLocale($this->locale->getLocale()),
        );

        $croct = Croct::plug(
            appId: $this->appId,
            apiKey: $this->apiKey,
            storage: $this->getStorage(),
            baseEndpointUrl: $this->baseEndpointUrl,
            context: $context,
        );

        return new VaryingResponseObserver($croct, function (): void {
            $this->personalized = true;
        });
    }

    private function resolveLocale(?string $detected): ?string
    {
        if (!$this->localeEnabled) {
            return $this->defaultLocale;
        }

        return $this->defaultLocale ?? $detected;
    }

    public function getStoredUserToken(): ?Token
    {
        return $this->getStorage()->getUserToken();
    }
}
