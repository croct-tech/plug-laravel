<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests;

use Croct\Plug\IdentityResolver;
use Croct\Plug\Laravel\CroctManager;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

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

    #[TestDox('Leaves an anonymous visitor untouched when there is no authenticated user.')]
    public function testKeepsAnonymousWhenGuest(): void
    {
        $manager = $this->createManager(userId: null);
        $manager->syncIdentity();

        self::assertNull($manager->getStoredUserToken());
    }

    #[TestDox('Identifies the visitor when a user logs in.')]
    public function testIdentifiesOnLogin(): void
    {
        $manager = $this->createManager(userId: 'alice');
        $manager->syncIdentity();

        self::assertTrue($manager->getStoredUserToken()?->isSubject('alice'));
    }

    #[TestDox('Anonymizes the visitor after the user logs out.')]
    public function testAnonymizesOnLogout(): void
    {
        $manager = $this->createManager(userId: null, userToken: self::issueToken('alice'));
        $manager->syncIdentity();

        self::assertTrue($manager->getStoredUserToken()?->isAnonymous());
    }

    #[TestDox('Leaves the token untouched when the user already matches.')]
    public function testKeepsTokenWhenUserMatches(): void
    {
        $token = self::issueToken('alice');
        $manager = $this->createManager(userId: 'alice', userToken: $token);
        $manager->syncIdentity();

        self::assertSame($token, $manager->getStoredUserToken()?->toString());
    }

    #[TestDox('Builds the Plug with a configured locale that overrides detection.')]
    public function testBuildsPlugWithConfiguredLocale(): void
    {
        $manager = $this->createManager(localeEnabled: false, defaultLocale: 'en-US');

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    private function createManager(
        ?string $userId = null,
        ?string $userToken = null,
        bool $localeEnabled = true,
        ?string $defaultLocale = null,
    ): CroctManager {
        $cookies = $userToken === null ? [] : ['ct.user_token' => $userToken];

        $request = Request::create(
            'https://example.com/',
            cookies: $cookies,
            server: ['HTTP_USER_AGENT' => 'Test/1.0', 'HTTP_REFERER' => 'https://referrer.example/'],
        );

        $identity = $this->createMock(IdentityResolver::class);
        $identity->method('getUserId')->willReturn($userId);

        $locale = $this->createMock(LocaleResolver::class);
        $locale->method('getLocale')->willReturn('en');

        return new CroctManager(
            $request,
            $identity,
            $locale,
            self::APP_ID,
            self::API_KEY,
            null,
            $localeEnabled,
            $defaultLocale,
        );
    }

    private static function issueToken(string $subject): string
    {
        return Token::issue(appId: self::APP_ID, subject: $subject, now: 1000)->toString();
    }
}
