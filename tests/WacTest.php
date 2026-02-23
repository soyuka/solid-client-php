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
use Dunglas\PhpSolidClient\Wac\AclDocument;
use Dunglas\PhpSolidClient\Wac\Authorization;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WacTest extends TestCase
{
    public function testAuthorizationConstants(): void
    {
        $this->assertSame('http://www.w3.org/ns/auth/acl#Read', Authorization::MODE_READ);
        $this->assertSame('http://www.w3.org/ns/auth/acl#Write', Authorization::MODE_WRITE);
        $this->assertSame('http://www.w3.org/ns/auth/acl#Append', Authorization::MODE_APPEND);
        $this->assertSame('http://www.w3.org/ns/auth/acl#Control', Authorization::MODE_CONTROL);
    }

    public function testAclDocumentToTurtle(): void
    {
        $acl = new AclDocument('http://pod.example/resource.acl', [
            new Authorization(
                'http://pod.example/resource',
                ['http://example.com/user#me'],
                [],
                [Authorization::MODE_READ, Authorization::MODE_WRITE, Authorization::MODE_CONTROL],
            ),
            new Authorization(
                'http://pod.example/resource',
                [],
                [Authorization::AGENT_CLASS_PUBLIC],
                [Authorization::MODE_READ],
            ),
        ]);

        $turtle = $acl->toTurtle();

        $this->assertStringContainsString('@prefix acl:', $turtle);
        $this->assertStringContainsString('acl:agent <http://example.com/user#me>', $turtle);
        $this->assertStringContainsString('acl:agentClass foaf:Agent', $turtle);
        $this->assertStringContainsString('acl:mode acl:Read', $turtle);
        $this->assertStringContainsString('acl:mode acl:Write', $turtle);
        $this->assertStringContainsString('acl:mode acl:Control', $turtle);
        $this->assertStringContainsString('acl:accessTo <http://pod.example/resource>', $turtle);
    }

    public function testAclDocumentFromTurtle(): void
    {
        $turtle = <<<'TURTLE'
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

<#owner>
    a acl:Authorization;
    acl:agent <http://example.com/user#me>;
    acl:mode acl:Read, acl:Write, acl:Control;
    acl:accessTo <http://pod.example/resource>;
    acl:default <http://pod.example/resource>.

<#public>
    a acl:Authorization;
    acl:agentClass foaf:Agent;
    acl:mode acl:Read;
    acl:accessTo <http://pod.example/resource>.
TURTLE;

        $acl = AclDocument::fromTurtle($turtle, 'http://pod.example/resource.acl', 'http://pod.example/resource');

        $this->assertSame('http://pod.example/resource.acl', $acl->aclUrl);
        $this->assertCount(2, $acl->authorizations);

        $owner = $acl->authorizations[0];
        $this->assertSame(['http://example.com/user#me'], $owner->agents);
        $this->assertContains(Authorization::MODE_READ, $owner->modes);
        $this->assertContains(Authorization::MODE_WRITE, $owner->modes);
        $this->assertContains(Authorization::MODE_CONTROL, $owner->modes);
        $this->assertTrue($owner->isDefault);

        $public = $acl->authorizations[1];
        $this->assertSame([Authorization::AGENT_CLASS_PUBLIC], $public->agentClasses);
        $this->assertSame([Authorization::MODE_READ], $public->modes);
        $this->assertFalse($public->isDefault);
    }

    public function testGetAcl(): void
    {
        $aclTurtle = <<<'TURTLE'
@prefix acl: <http://www.w3.org/ns/auth/acl#>.
@prefix foaf: <http://xmlns.com/foaf/0.1/>.

<#owner>
    a acl:Authorization;
    acl:agent <http://example.com/user#me>;
    acl:mode acl:Read, acl:Write, acl:Control;
    acl:accessTo <http://pod.example/data/file.ttl>.
TURTLE;

        $httpClient = new MockHttpClient([
            // HEAD response with Link: rel="acl"
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type' => 'text/turtle',
                    'Link' => '<http://pod.example/data/file.ttl.acl>; rel="acl"',
                ],
            ]),
            // GET ACL document
            new MockResponse($aclTurtle, [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'text/turtle'],
            ]),
        ]);
        $client = new SolidClient($httpClient);

        $acl = $client->getAcl('http://pod.example/data/file.ttl');

        $this->assertSame('http://pod.example/data/file.ttl.acl', $acl->aclUrl);
        $this->assertCount(1, $acl->authorizations);
        $this->assertSame(['http://example.com/user#me'], $acl->authorizations[0]->agents);
    }

    public function testPutAcl(): void
    {
        $response = new MockResponse('', ['http_code' => 201]);
        $httpClient = new MockHttpClient($response);
        $client = new SolidClient($httpClient);

        $acl = new AclDocument('http://pod.example/data/file.ttl.acl', [
            new Authorization(
                'http://pod.example/data/file.ttl',
                ['http://example.com/user#me'],
                [],
                [Authorization::MODE_READ, Authorization::MODE_WRITE],
            ),
        ]);

        $client->putAcl('http://pod.example/data/file.ttl', $acl);

        $this->assertSame('PUT', $response->getRequestMethod());
        $this->assertSame('http://pod.example/data/file.ttl.acl', $response->getRequestUrl());
        $this->assertStringContainsString('acl:agent', $response->getRequestOptions()['body']);
    }

    public function testAclDocumentWithDefault(): void
    {
        $acl = new AclDocument('http://pod.example/container/.acl', [
            new Authorization(
                'http://pod.example/container/',
                ['http://example.com/user#me'],
                [],
                [Authorization::MODE_READ, Authorization::MODE_WRITE],
                true,
            ),
        ]);

        $turtle = $acl->toTurtle();

        $this->assertStringContainsString('acl:default', $turtle);
    }
}
