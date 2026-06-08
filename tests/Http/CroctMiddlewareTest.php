<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests\Http;

use Croct\Plug\Exception\MalformedTokenException;
use Croct\Plug\IdentityResolver;
use Croct\Plug\Laravel\CroctManager;
use Croct\Plug\Laravel\Http\CroctMiddleware;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Token;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CroctMiddleware::class)]
#[TestDox('The Croct middleware')]
final class CroctMiddlewareTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    private const LOADER = 'https://cdn.example/plug.js';

    #[TestDox('Injects the bootstrap before the closing head tag.')]
    public function testInjectsScriptIntoHead(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head'),
            Request::create('/'),
            new Response('<html><head></head><body></body></html>'),
        );

        self::assertStringContainsString('croct.plug(', (string) $response->getContent());
        self::assertStringContainsString('</script></head>', (string) $response->getContent());
    }

    #[TestDox('Injects the bootstrap before the closing body tag.')]
    public function testInjectsScriptIntoBody(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('body'),
            Request::create('/'),
            new Response('<html><head></head><body>Hi</body></html>'),
        );

        self::assertStringContainsString('</script></body>', (string) $response->getContent());
    }

    #[TestDox('Does not inject when auto-injection is disabled.')]
    public function testSkipsInjectionWhenDisabled(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head', autoInject: false),
            Request::create('/'),
            new Response('<html><head></head></html>'),
        );

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores XML HTTP requests.')]
    public function testSkipsXmlHttpRequests(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head'),
            Request::create('/', server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            new Response('<html><head></head></html>'),
        );

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores redirects.')]
    public function testSkipsRedirects(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head'),
            Request::create('/'),
            new Response('<html><head></head></html>', 302),
        );

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores non-HTML responses.')]
    public function testSkipsNonHtmlResponses(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head'),
            Request::create('/'),
            new Response('{}', 200, ['Content-Type' => 'application/json']),
        );

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Leaves HTML without the anchor untouched.')]
    public function testSkipsResponsesWithoutAnchor(): void
    {
        $response = $this->dispatch(
            $this->createMiddleware('head'),
            Request::create('/'),
            new Response('just a fragment'),
        );

        self::assertSame('just a fragment', $response->getContent());
    }

    #[TestDox('Writes the session cookies and marks the response private when personalized.')]
    public function testWritesCookiesAndMarksPrivateWhenPersonalized(): void
    {
        $manager = $this->createManager();
        // Reading the client id flags the request and queues the session cookie.
        $manager->getPlug()->getClientId();

        $response = $this->dispatch(
            new CroctMiddleware($manager, true, self::LOADER, 'head'),
            Request::create('/'),
            new Response('<html><head></head><body></body></html>'),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $names = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $response->headers->getCookies(),
        );

        self::assertContains('ct.client_id', $names);
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Identifies the visitor when a user is authenticated.')]
    public function testIdentifiesAuthenticatedUser(): void
    {
        $manager = $this->createManager(identity: $this->identity('alice'));

        $response = $this->dispatch(
            new CroctMiddleware($manager, false, self::LOADER, 'head'),
            Request::create('/'),
            new Response('ok'),
        );

        self::assertTrue(Token::parse(self::userTokenCookie($response))->isSubject('alice'));
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Anonymizes the visitor after the user logs out.')]
    public function testAnonymizesGuest(): void
    {
        $manager = $this->createManager(userToken: self::token('alice'), identity: $this->identity(null));

        $response = $this->dispatch(
            new CroctMiddleware($manager, false, self::LOADER, 'head'),
            Request::create('/'),
            new Response('ok'),
        );

        self::assertTrue(Token::parse(self::userTokenCookie($response))->isAnonymous());
    }

    private function createMiddleware(string $placement, bool $autoInject = true): CroctMiddleware
    {
        return new CroctMiddleware(
            $this->createManager(),
            $autoInject,
            self::LOADER,
            $placement,
        );
    }

    private function createManager(?string $userToken = null, ?IdentityResolver $identity = null): CroctManager
    {
        $cookies = $userToken === null ? [] : ['ct.user_token' => $userToken];

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en');

        return new CroctManager(
            Request::create('/', cookies: $cookies),
            $locale,
            self::APP_ID,
            self::API_KEY,
            identity: $identity,
        );
    }

    private static function userTokenCookie(Response $response): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'ct.user_token') {
                return (string) $cookie->getValue();
            }
        }

        return '';
    }

    private function identity(?string $userId): IdentityResolver
    {
        $identity = $this->createMock(IdentityResolver::class);
        $identity->method('getUserId')->willReturn($userId);

        return $identity;
    }

    private function dispatch(CroctMiddleware $middleware, Request $request, Response $response): Response
    {
        return $middleware->handle($request, static fn (): Response => $response);
    }

    private static function token(string $subject): string
    {
        return Token::issue(appId: self::APP_ID, subject: $subject, now: 1000)->toString();
    }
}
