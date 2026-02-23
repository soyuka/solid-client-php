<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Tests;

use Dunglas\PhpSolidClient\JsonLdParser;
use PHPUnit\Framework\TestCase;

class JsonLdParserTest extends TestCase
{
    public function testParseSingleObject(): void
    {
        $jsonLd = json_encode([
            '@id' => 'http://example.com/thing',
            '@type' => ['http://schema.org/Thing'],
            'http://schema.org/name' => [['@value' => 'Test']],
        ]);

        $result = JsonLdParser::parse($jsonLd);

        $this->assertCount(1, $result);
        $this->assertSame('http://example.com/thing', $result[0]['@id']);
    }

    public function testParseExpandedArray(): void
    {
        $jsonLd = json_encode([
            [
                '@id' => 'http://example.com/a',
                '@type' => ['http://schema.org/Thing'],
            ],
            [
                '@id' => 'http://example.com/b',
            ],
        ]);

        $result = JsonLdParser::parse($jsonLd);

        $this->assertCount(2, $result);
        $this->assertSame('http://example.com/a', $result[0]['@id']);
        $this->assertSame('http://example.com/b', $result[1]['@id']);
    }

    public function testParseLdpContainer(): void
    {
        $jsonLd = json_encode([
            '@id' => 'http://pod.example/container/',
            '@type' => ['http://www.w3.org/ns/ldp#BasicContainer'],
            'http://www.w3.org/ns/ldp#contains' => [
                ['@id' => 'file.txt'],
                ['@id' => 'sub/', '@type' => ['http://www.w3.org/ns/ldp#BasicContainer']],
            ],
        ]);

        $result = JsonLdParser::parse($jsonLd);

        $this->assertCount(1, $result);
        $contains = $result[0]['http://www.w3.org/ns/ldp#contains'];
        $this->assertCount(2, $contains);
        $this->assertSame('file.txt', $contains[0]['@id']);
        $this->assertSame('sub/', $contains[1]['@id']);
    }

    public function testFindById(): void
    {
        $nodes = [
            ['@id' => 'http://example.com/a', 'name' => 'A'],
            ['@id' => 'http://example.com/b', 'name' => 'B'],
        ];

        $found = JsonLdParser::findById($nodes, 'http://example.com/b');

        $this->assertNotNull($found);
        $this->assertSame('B', $found['name']);

        $this->assertNull(JsonLdParser::findById($nodes, 'http://example.com/c'));
    }
}
