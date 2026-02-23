<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Tests;

use Dunglas\PhpSolidClient\ResourceMetadata;
use PHPUnit\Framework\TestCase;

class ResourceMetadataTest extends TestCase
{
    public function testFromResponseHeaders(): void
    {
        $headers = [
            'content-type' => ['text/turtle; charset=utf-8'],
            'content-length' => ['4567'],
            'last-modified' => ['Mon, 23 Feb 2026 12:00:00 GMT'],
            'link' => [
                '<http://www.w3.org/ns/ldp#BasicContainer>; rel="type"',
                '<http://pod.example/resource.acl>; rel="acl"',
            ],
            'wac-allow' => ['user="read write append control",public="read"'],
        ];

        $metadata = ResourceMetadata::fromResponseHeaders($headers);

        $this->assertSame('text/turtle', $metadata->contentType);
        $this->assertSame(4567, $metadata->contentLength);
        $this->assertInstanceOf(\DateTimeImmutable::class, $metadata->lastModified);
        $this->assertSame('http://www.w3.org/ns/ldp#BasicContainer', $metadata->ldpType);
        $this->assertTrue($metadata->isContainer());
        $this->assertSame('http://pod.example/resource.acl', $metadata->aclUrl);
        $this->assertSame(['read', 'write', 'append', 'control'], $metadata->wacAllow['user']);
        $this->assertSame(['read'], $metadata->wacAllow['public']);
    }

    public function testResourceNotContainer(): void
    {
        $headers = [
            'content-type' => ['application/ld+json'],
            'link' => ['<http://www.w3.org/ns/ldp#Resource>; rel="type"'],
        ];

        $metadata = ResourceMetadata::fromResponseHeaders($headers);

        $this->assertFalse($metadata->isContainer());
        $this->assertSame('http://www.w3.org/ns/ldp#Resource', $metadata->ldpType);
    }

    public function testEmptyHeaders(): void
    {
        $metadata = ResourceMetadata::fromResponseHeaders([]);

        $this->assertNull($metadata->contentType);
        $this->assertNull($metadata->contentLength);
        $this->assertNull($metadata->lastModified);
        $this->assertNull($metadata->ldpType);
        $this->assertNull($metadata->aclUrl);
        $this->assertSame([], $metadata->wacAllow);
    }
}
