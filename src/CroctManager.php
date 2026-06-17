<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel;

use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Cookie;
use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Croct;
use Croct\Plug\IdentityResolver;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Plug;
use Croct\Plug\RequestContext;
use Croct\Plug\VaryingResponseObserver;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface as Logger;

/**
 * Builds and manages the request-scoped plug for the current Laravel request.
 *
 * Besides creating the plug, it reconciles the visitor identity with the authenticated user and
 * exposes the session cookies and the client-side bootstrap options for the response.
 */
final class CroctManager
{
    /**
     * Request attribute flagging that the response varies by visitor.
     */
    public const PERSONALIZED_ATTRIBUTE = '_croct_personalized';

    private Request $request;

    private LocaleResolver $locale;

    private string $appId;

    private string $apiKey;

    private ?string $baseEndpointUrl;

    private bool $localeEnabled;

    private ?string $defaultLocale;

    private ?string $cookieDomain;

    private bool $cookieSecure;

    private string $cookieSameSite;

    private ?ContentProvider $contentProvider;

    private ?Logger $logger;

    private int $tokenDuration;

    private ?IdentityResolver $identity;

    private ?Plug $plug = null;

    private ?CookieStorage $storage = null;

    public function __construct(
        Request $request,
        LocaleResolver $locale,
        string $appId,
        string $apiKey,
        ?string $baseEndpointUrl = null,
        bool $localeEnabled = true,
        ?string $defaultLocale = null,
        ?string $cookieDomain = null,
        bool $cookieSecure = true,
        string $cookieSameSite = 'none',
        ?ContentProvider $contentProvider = null,
        ?Logger $logger = null,
        int $tokenDuration = Croct::DEFAULT_TOKEN_DURATION,
        ?IdentityResolver $identity = null,
    ) {
        $this->request = $request;
        $this->locale = $locale;
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->baseEndpointUrl = $baseEndpointUrl;
        $this->localeEnabled = $localeEnabled;
        $this->defaultLocale = $defaultLocale;
        $this->cookieDomain = $cookieDomain;
        $this->cookieSecure = $cookieSecure;
        $this->cookieSameSite = $cookieSameSite;
        $this->contentProvider = $contentProvider;
        $this->logger = $logger;
        $this->tokenDuration = $tokenDuration;
        $this->identity = $identity;
    }

    public function getPlug(): Plug
    {
        return $this->plug ??= $this->createPlug();
    }

    public function isPersonalized(): bool
    {
        return $this->request->attributes->get(self::PERSONALIZED_ATTRIBUTE) === true;
    }

    /**
     * @return list<Cookie>
     */
    public function getResponseCookies(): array
    {
        return $this->getStorage()->getResponseCookies();
    }

    /**
     * Resolves the visitor token and reports whether it changed.
     *
     * Resolving issues or refreshes the token through the session when it is missing, expired, or
     * out of sync with the authenticated user, saving it back to the storage. Returns true when the
     * token was (re)issued, so the caller writes the new cookie and keeps the response private.
     */
    public function reconcile(): bool
    {
        $stored = $this->getStorage()->getUserToken();
        $resolved = $this->getPlug()->getUserToken();

        return $stored === null || !$stored->equals($resolved);
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
            previewToken: RequestContext::resolvePreviewToken(self::getPreviewToken($this->request)),
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
            identity: $this->identity,
            baseEndpointUrl: $this->baseEndpointUrl,
            tokenDuration: $this->tokenDuration,
            contentProvider: $this->contentProvider,
            context: $context,
            logger: $this->logger,
        );

        return new VaryingResponseObserver($croct, function (): void {
            $this->request->attributes->set(self::PERSONALIZED_ATTRIBUTE, true);
        });
    }

    private static function getPreviewToken(Request $request): ?string
    {
        $value = $request->query->getString(RequestContext::PREVIEW_QUERY_PARAMETER);

        return $value !== '' ? $value : null;
    }

    private function resolveLocale(?string $detected): ?string
    {
        if (!$this->localeEnabled) {
            return $this->defaultLocale;
        }

        return $this->defaultLocale ?? $detected;
    }
}
