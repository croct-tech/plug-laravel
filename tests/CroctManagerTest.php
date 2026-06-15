<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\Content\NullContentProvider;
use Croct\Plug\Exception\MalformedTokenException;
use Croct\Plug\IdentityResolver;
use Croct\Plug\Laravel\CroctManager;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(CroctManager::class)]
#[TestDox('The Croct manager')]
final class CroctManagerTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Exposes the visitor-independent browser plug options.')]
    public function testExposesPlugOptions(): void
    {
        $options = $this->createManager()->getPlugOptions();

        self::assertSame(self::APP_ID, $options['appId']);
        self::assertTrue($options['disableCidMirroring']);
        self::assertArrayHasKey('cookie', $options);
    }

    #[TestDox('Flags the request as personalized when the visitor session is used.')]
    public function testFlagsPersonalizedWhenSessionUsed(): void
    {
        $manager = $this->createManager();

        self::assertFalse($manager->isPersonalized());

        $manager->getPlug()->getClientId();

        self::assertTrue($manager->isPersonalized());
        self::assertNotEmpty($manager->getResponseCookies());
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Issues a token for the authenticated user and reports the change.')]
    public function testReconcilesIdentityOnLogin(): void
    {
        $manager = $this->createManager(identity: $this->identity('alice'));

        self::assertTrue($manager->reconcile());
        self::assertTrue(Token::parse(self::userTokenCookie($manager))->isSubject('alice'));
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Re-issues anonymously after the user logs out and reports the change.')]
    public function testReconcilesIdentityOnLogout(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->toString();
        $manager = $this->createManager(userToken: $token, identity: $this->identity(null));

        self::assertTrue($manager->reconcile());
        self::assertTrue(Token::parse(self::userTokenCookie($manager))->isAnonymous());
    }

    #[TestDox('Keeps a matching token and reports no change.')]
    public function testKeepsMatchingToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->toString();
        $manager = $this->createManager(userToken: $token, identity: $this->identity('alice'));

        self::assertFalse($manager->reconcile());
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Issues an anonymous token when none exists, even without an identity resolver.')]
    public function testIssuesTokenWithoutIdentity(): void
    {
        $manager = $this->createManager();

        self::assertTrue($manager->reconcile());
        self::assertTrue(Token::parse(self::userTokenCookie($manager))->isAnonymous());
    }

    #[TestDox('Keeps a valid anonymous token without an identity resolver.')]
    public function testKeepsValidTokenWithoutIdentity(): void
    {
        $token = Token::issue(appId: self::APP_ID, now: 1000)->toString();
        $manager = $this->createManager(userToken: $token);

        self::assertFalse($manager->reconcile());
    }

    #[TestDox('Re-issues an expired token and reports the change.')]
    public function testReissuesExpiredToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, now: 1000)->withDuration(3600, 1000)->toString();
        $manager = $this->createManager(userToken: $token);

        self::assertTrue($manager->reconcile());
    }

    #[TestDox('Builds the Plug with a configured locale that overrides detection.')]
    public function testBuildsPlugWithConfiguredLocale(): void
    {
        $manager = $this->createManager(localeEnabled: false, defaultLocale: 'en-US');

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with the preview token from the request.')]
    public function testBuildsPlugWithPreviewToken(): void
    {
        $manager = $this->createManager(url: 'https://example.com/?croct-preview=preview-token');

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with a content provider, logger and token duration.')]
    public function testBuildsPlugWithOptions(): void
    {
        $manager = new CroctManager(
            Request::create('https://example.com/'),
            $this->createMock(LocaleResolver::class),
            self::APP_ID,
            self::API_KEY,
            contentProvider: new NullContentProvider(),
            logger: new NullLogger(),
            tokenDuration: 3600,
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    private function createManager(
        bool $localeEnabled = true,
        ?string $defaultLocale = null,
        string $url = 'https://example.com/',
        ?string $userToken = null,
        ?IdentityResolver $identity = null,
    ): CroctManager {
        $cookies = $userToken === null ? [] : ['ct_user_token' => $userToken];

        $request = Request::create(
            $url,
            cookies: $cookies,
            server: ['HTTP_USER_AGENT' => 'Test/1.0', 'HTTP_REFERER' => 'https://referrer.example/'],
        );

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en');

        return new CroctManager(
            $request,
            $locale,
            self::APP_ID,
            self::API_KEY,
            null,
            $localeEnabled,
            $defaultLocale,
            identity: $identity,
        );
    }

    private function identity(?string $userId): IdentityResolver
    {
        $identity = $this->createMock(IdentityResolver::class);
        $identity->method('getUserId')->willReturn($userId);

        return $identity;
    }

    private static function userTokenCookie(CroctManager $manager): string
    {
        foreach ($manager->getResponseCookies() as $cookie) {
            if ($cookie->getName() === 'ct_user_token') {
                return $cookie->getValue();
            }
        }

        return '';
    }
}
