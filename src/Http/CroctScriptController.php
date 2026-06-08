<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Http;

use Croct\Plug\CroctScriptProvider;
use Illuminate\Http\Request;
use Psr\Http\Client\ClientExceptionInterface as ClientException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the client-side SDK from a first-party path instead of a third-party CDN.
 *
 * It relays the upstream response verbatim, handling conditional requests locally against the
 * relayed validators.
 */
final class CroctScriptController
{
    private CroctScriptProvider $provider;

    public function __construct(CroctScriptProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @throws ClientException If the upstream request fails.
     */
    public function __invoke(Request $request): Response
    {
        $script = $this->provider->load(self::collectHeaders($request));

        $response = new Response($script->getContent(), $script->getStatusCode());

        foreach ($script->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        // The cache varies on Accept-Encoding, so downstream caches must too.
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->isNotModified($request);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private static function collectHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $value = $request->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
