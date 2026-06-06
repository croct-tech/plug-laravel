<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Http;

use Closure;
use Croct\Plug\Cookie;
use Croct\Plug\CroctScript;
use Croct\Plug\Laravel\CroctManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Injects the client-side SDK bootstrap and writes the visitor session to the response.
 *
 * It runs on every web request: it keeps the visitor identity in sync before the controller, then
 * injects the bootstrap into HTML responses and, when the request used visitor-specific data, writes
 * the session cookies and marks the response private so it is never shared-cached.
 */
final class CroctMiddleware
{
    private CroctManager $manager;

    private bool $autoInject;

    private string $scriptSrc;

    private string $placement;

    public function __construct(CroctManager $manager, bool $autoInject, string $scriptSrc, string $placement)
    {
        $this->manager = $manager;
        $this->autoInject = $autoInject;
        $this->scriptSrc = $scriptSrc;
        $this->placement = $placement;
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->manager->syncIdentity();

        $response = $next($request);

        if ($this->autoInject) {
            $this->injectScript($request, $response);
        }

        if ($this->manager->isPersonalized()) {
            foreach ($this->manager->getResponseCookies() as $cookie) {
                $response->headers->setCookie(self::createCookie($cookie));
            }

            // The response depends on the visitor (content and/or session cookies): never shared-cache it.
            $response->setPrivate();
        }

        return $response;
    }

    private function injectScript(Request $request, Response $response): void
    {
        if ($request->ajax()
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->isRedirection()
        ) {
            return;
        }

        if (!\str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        $content = (string) $response->getContent();
        $anchor = $this->placement === 'head' ? '</head>' : '</body>';
        $position = \strripos($content, $anchor);

        if ($position === false) {
            return;
        }

        $script = (string) new CroctScript($this->scriptSrc, $this->manager->getPlugOptions());

        $response->setContent(\substr_replace($content, $script, $position, 0));
    }

    private static function createCookie(Cookie $cookie): SymfonyCookie
    {
        return SymfonyCookie::create(
            $cookie->getName(),
            $cookie->getValue(),
            $cookie->getExpiration() ?? 0,
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            // The client SDK reads these cookies, so they must never be HTTP-only.
            false,
            false,
            [
                'lax' => SymfonyCookie::SAMESITE_LAX,
                'strict' => SymfonyCookie::SAMESITE_STRICT,
                'none' => SymfonyCookie::SAMESITE_NONE,
            ][\strtolower((string) $cookie->getSameSite())] ?? null,
        );
    }
}
