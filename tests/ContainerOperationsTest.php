<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Tests;

use Dunglas\PhpSolidClient\ContainerEntry;
use Dunglas\PhpSolidClient\SolidClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ContainerOperationsTest extends TestCase
{
    public function testGetResourceMetadata(): void
    {
        $response = new MockResponse('', [
            'http_code' => 200,
            'response_headers' => [
                'Content-Type' => 'text/turtle',
                'Content-Length' => '512',
                'Link' => '<http://www.w3.org/ns/ldp#Resource>; rel="type"',
                'WAC-Allow' => 'user="read write",public="read"',
            ],
        ]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $metadata = $client->getResourceMetadata('http://pod.example/resource');

        $this->assertSame('text/turtle', $metadata->contentType);
        $this->assertSame(512, $metadata->contentLength);
        $this->assertSame('http://www.w3.org/ns/ldp#Resource', $metadata->ldpType);
        $this->assertFalse($metadata->isContainer());
        $this->assertSame(['read', 'write'], $metadata->wacAllow['user']);
        $this->assertSame(['read'], $metadata->wacAllow['public']);
    }

    public function testGetContainerContents(): void
    {
        $jsonLd = json_encode([
            '@id' => 'http://pod.example/container/',
            '@type' => ['http://www.w3.org/ns/ldp#BasicContainer'],
            'http://www.w3.org/ns/ldp#contains' => [
                ['@id' => 'http://pod.example/container/file.txt'],
                [
                    '@id' => 'http://pod.example/container/sub/',
                    '@type' => ['http://www.w3.org/ns/ldp#BasicContainer'],
                ],
            ],
        ]);

        $response = new MockResponse($jsonLd, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $entries = $client->getContainerContents('http://pod.example/container/');

        $this->assertCount(2, $entries);
        $this->assertInstanceOf(ContainerEntry::class, $entries[0]);
        $this->assertSame('http://pod.example/container/file.txt', $entries[0]->url);
        $this->assertFalse($entries[0]->isContainer);
        $this->assertSame('http://pod.example/container/sub/', $entries[1]->url);
        $this->assertTrue($entries[1]->isContainer);
    }

    public function testGetContainerContentsWithRelativeIds(): void
    {
        $jsonLd = json_encode([
            '@id' => './',
            '@type' => ['http://www.w3.org/ns/ldp#BasicContainer'],
            'http://www.w3.org/ns/ldp#contains' => [
                ['@id' => 'file.txt'],
                ['@id' => 'sub/', '@type' => ['http://www.w3.org/ns/ldp#BasicContainer']],
            ],
        ]);

        $response = new MockResponse($jsonLd, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $entries = $client->getContainerContents('http://pod.example/data/');

        $this->assertCount(2, $entries);
        $this->assertSame('http://pod.example/data/file.txt', $entries[0]->url);
        $this->assertSame('http://pod.example/data/sub/', $entries[1]->url);
    }
}
