<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Tests;

use Dunglas\PhpSolidClient\IriHelper;
use PHPUnit\Framework\TestCase;

class IriHelperTest extends TestCase
{
    private const BASE = 'http://a/b/c/d;p?q';

    /**
     * RFC 3986 §5.4 — Normal examples.
     *
     * @dataProvider rfc3986NormalExamplesProvider
     */
    public function testRfc3986NormalExamples(string $reference, string $expected): void
    {
        $this->assertSame($expected, IriHelper::resolve(self::BASE, $reference));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rfc3986NormalExamplesProvider(): iterable
    {
        yield 'g:h' => ['g:h', 'g:h'];
        yield 'g' => ['g', 'http://a/b/c/g'];
        yield './g' => ['./g', 'http://a/b/c/g'];
        yield 'g/' => ['g/', 'http://a/b/c/g/'];
        yield '/g' => ['/g', 'http://a/g'];
        yield '//g' => ['//g', 'http://g'];
        yield '?y' => ['?y', 'http://a/b/c/d;p?y'];
        yield 'g?y' => ['g?y', 'http://a/b/c/g?y'];
        yield '#s' => ['#s', 'http://a/b/c/d;p?q#s'];
        yield 'g#s' => ['g#s', 'http://a/b/c/g#s'];
        yield 'g?y#s' => ['g?y#s', 'http://a/b/c/g?y#s'];
        yield ';x' => [';x', 'http://a/b/c/;x'];
        yield 'g;x' => ['g;x', 'http://a/b/c/g;x'];
        yield 'g;x?y#s' => ['g;x?y#s', 'http://a/b/c/g;x?y#s'];
        yield 'empty' => ['', 'http://a/b/c/d;p?q'];
        yield '.' => ['.', 'http://a/b/c/'];
        yield './' => ['./', 'http://a/b/c/'];
        yield '..' => ['..', 'http://a/b/'];
        yield '../' => ['../', 'http://a/b/'];
        yield '../g' => ['../g', 'http://a/b/g'];
        yield '../..' => ['../..', 'http://a/'];
        yield '../../' => ['../../', 'http://a/'];
        yield '../../g' => ['../../g', 'http://a/g'];
    }

    public function testSolidPodRelativeId(): void
    {
        $base = 'http://localhost:3000/test/';
        $this->assertSame('http://localhost:3000/test/file.ttl', IriHelper::resolve($base, 'file.ttl'));
        $this->assertSame('http://localhost:3000/test/sub/', IriHelper::resolve($base, 'sub/'));
    }

    public function testAbsoluteReferenceUnchanged(): void
    {
        $this->assertSame(
            'https://other.example/path',
            IriHelper::resolve('http://example.com/base/', 'https://other.example/path'),
        );
    }
}
