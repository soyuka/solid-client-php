<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Tests;

use Dunglas\PhpSolidClient\SolidClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SolidClientTest extends TestCase
{
    private static function findHeader(array $headers, string $name): ?string
    {
        $prefix = $name.': ';
        foreach ($headers as $header) {
            if (str_starts_with($header, $prefix)) {
                return substr($header, \strlen($prefix));
            }
        }

        return null;
    }

    public function testPut(): void
    {
        $response = new MockResponse('', ['http_code' => 201]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->put('http://pod.example/resource', '<> a <http://schema.org/Thing> .');

        $this->assertSame('PUT', $response->getRequestMethod());
        $this->assertSame('http://pod.example/resource', $response->getRequestUrl());
        $this->assertSame('<> a <http://schema.org/Thing> .', $response->getRequestOptions()['body']);
        $this->assertSame('text/turtle', self::findHeader($response->getRequestOptions()['headers'], 'Content-Type'));
    }

    public function testPutContainer(): void
    {
        $response = new MockResponse('', ['http_code' => 201]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->put('http://pod.example/container/', null, true);

        $this->assertSame('PUT', $response->getRequestMethod());
        $this->assertStringContainsString('ldp#BasicContainer', self::findHeader($response->getRequestOptions()['headers'], 'Link') ?? '');
    }

    public function testPutCustomContentType(): void
    {
        $response = new MockResponse('', ['http_code' => 201]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->put('http://pod.example/resource', '{}', false, [
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertSame('application/ld+json', self::findHeader($response->getRequestOptions()['headers'], 'Content-Type'));
    }

    public function testHead(): void
    {
        $response = new MockResponse('', [
            'http_code' => 200,
            'response_headers' => [
                'Content-Type' => 'text/turtle',
                'Content-Length' => '1234',
            ],
        ]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->head('http://pod.example/resource');

        $this->assertSame('HEAD', $response->getRequestMethod());
        $this->assertSame('http://pod.example/resource', $response->getRequestUrl());
    }

    public function testDelete(): void
    {
        $response = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->delete('http://pod.example/resource');

        $this->assertSame('DELETE', $response->getRequestMethod());
        $this->assertSame('http://pod.example/resource', $response->getRequestUrl());
    }

    public function testPatchSparqlUpdate(): void
    {
        $sparql = 'INSERT DATA { <> <http://schema.org/name> "Test" . }';
        $response = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->patch('http://pod.example/resource', $sparql);

        $this->assertSame('PATCH', $response->getRequestMethod());
        $this->assertSame($sparql, $response->getRequestOptions()['body']);
        $this->assertSame('application/sparql-update', self::findHeader($response->getRequestOptions()['headers'], 'Content-Type'));
    }

    public function testPatchN3(): void
    {
        $n3Patch = '@prefix solid: <http://www.w3.org/ns/solid/terms#>. _:patch a solid:InsertDeletePatch .';
        $response = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->patch('http://pod.example/resource', $n3Patch, 'text/n3');

        $this->assertSame('text/n3', self::findHeader($response->getRequestOptions()['headers'], 'Content-Type'));
    }

    public function testPost(): void
    {
        $response = new MockResponse('', [
            'http_code' => 201,
            'response_headers' => ['Location' => 'http://pod.example/container/new-resource'],
        ]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $client->post('http://pod.example/container/', '<> a <http://schema.org/Thing> .', 'new-resource');

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('new-resource', self::findHeader($response->getRequestOptions()['headers'], 'Slug'));
        $this->assertStringContainsString('ldp#Resource', self::findHeader($response->getRequestOptions()['headers'], 'Link') ?? '');
    }

    public function testGet(): void
    {
        $body = '<> a <http://schema.org/Thing> .';
        $response = new MockResponse($body, ['http_code' => 200]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $result = $client->get('http://pod.example/resource');

        $this->assertSame('GET', $response->getRequestMethod());
        $this->assertSame($body, $result->getContent());
    }
}
