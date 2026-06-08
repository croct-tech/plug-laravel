<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests\Http;

use Croct\Plug\CroctScriptProvider;
use Croct\Plug\Laravel\Http\CroctScriptController;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Http\Mock\Client as MockClient;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface as ClientException;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CroctScriptController::class)]
#[TestDox('The first-party script controller')]
final class CroctScriptControllerTest extends TestCase
{
    private const LOADER = 'https://cdn.example/plug.js';

    #[TestDox('Relays the upstream response verbatim, with a Vary header and without cookies.')]
    public function testRelaysUpstreamResponse(): void
    {
        $upstream = new PsrResponse(200, [
            'Content-Type' => 'text/javascript',
            'Content-Encoding' => 'br',
            'Cache-Control' => 'public, max-age=600',
            'Set-Cookie' => 'session=1',
        ], '// plug');

        $request = Request::create('/_croct/plug.js', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'br, gzip']);

        $response = $this->dispatch($request, $upstream);

        self::assertSame('// plug', (string) $response->getContent());
        self::assertSame('text/javascript', $response->headers->get('Content-Type'));
        self::assertSame('br', $response->headers->get('Content-Encoding'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('Accept-Encoding', $response->headers->get('Vary'));
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    #[TestDox('Returns 304 when the relayed validator matches the request.')]
    public function testReturnsNotModified(): void
    {
        $upstream = new PsrResponse(200, ['ETag' => '"v1"'], '// plug');

        $request = Request::create('/_croct/plug.js', 'GET');
        $request->headers->set('If-None-Match', '"v1"');

        self::assertSame(304, $this->dispatch($request, $upstream)->getStatusCode());
    }

    /**
     * @throws ClientException If the upstream request fails.
     */
    private function dispatch(Request $request, PsrResponse $upstream): Response
    {
        $httpClient = new MockClient();
        $httpClient->addResponse($upstream);

        $provider = new CroctScriptProvider(
            $httpClient,
            new HttpFactory(),
            new CacheRepository(new ArrayStore()),
            self::LOADER,
        );

        return (new CroctScriptController($provider))($request);
    }
}
