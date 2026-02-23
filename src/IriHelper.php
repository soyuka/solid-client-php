<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient;

/**
 * RFC 3986 §5 relative IRI resolution.
 */
final class IriHelper
{
    /**
     * Resolves a relative reference against a base URI per RFC 3986 §5.3.
     */
    public static function resolve(string $base, string $reference): string
    {
        // If the reference is already absolute, return it
        $r = self::parse($reference);
        if (null !== $r['scheme']) {
            return self::recompose(
                $r['scheme'],
                $r['authority'],
                self::removeDotSegments($r['path']),
                $r['query'],
                $r['fragment'],
            );
        }

        $b = self::parse($base);

        if (null !== $r['authority']) {
            return self::recompose(
                $b['scheme'],
                $r['authority'],
                self::removeDotSegments($r['path']),
                $r['query'],
                $r['fragment'],
            );
        }

        if ('' === $r['path']) {
            $path = $b['path'];
            $query = $r['query'] ?? $b['query'];
        } else {
            if (str_starts_with($r['path'], '/')) {
                $path = self::removeDotSegments($r['path']);
            } else {
                $path = self::merge($b, $r['path']);
                $path = self::removeDotSegments($path);
            }
            $query = $r['query'];
        }

        return self::recompose($b['scheme'], $b['authority'], $path, $query, $r['fragment']);
    }

    /**
     * @return array{scheme: ?string, authority: ?string, path: string, query: ?string, fragment: ?string}
     */
    private static function parse(string $uri): array
    {
        // RFC 3986 Appendix B regex
        preg_match('~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?~', $uri, $m);

        return [
            'scheme' => isset($m[2]) && '' !== $m[2] ? $m[2] : null,
            'authority' => isset($m[3]) && '' !== $m[3] ? $m[4] : null,
            'path' => $m[5] ?? '',
            'query' => isset($m[6]) && '' !== $m[6] ? $m[7] : null,
            'fragment' => isset($m[8]) && '' !== $m[8] ? $m[9] : null,
        ];
    }

    /**
     * @param array{scheme: ?string, authority: ?string, path: string, query: ?string, fragment: ?string} $base
     */
    private static function merge(array $base, string $referencePath): string
    {
        if (null !== $base['authority'] && '' === $base['path']) {
            return '/'.$referencePath;
        }

        $lastSlash = strrpos($base['path'], '/');

        return false !== $lastSlash
            ? substr($base['path'], 0, $lastSlash + 1).$referencePath
            : $referencePath;
    }

    private static function removeDotSegments(string $path): string
    {
        $output = [];
        $segments = explode('/', $path);
        $absolute = str_starts_with($path, '/');
        $trailingSlash = false;

        foreach ($segments as $segment) {
            if ('.' === $segment) {
                $trailingSlash = true;
                continue;
            }
            if ('..' === $segment) {
                array_pop($output);
                $trailingSlash = true;
                continue;
            }
            $trailingSlash = false;
            $output[] = $segment;
        }

        $result = implode('/', $output);
        if ($trailingSlash && !str_ends_with($result, '/')) {
            $result .= '/';
        }

        // Preserve leading slash for absolute paths
        if ($absolute && !str_starts_with($result, '/')) {
            $result = '/'.$result;
        }

        return $result;
    }

    private static function recompose(?string $scheme, ?string $authority, string $path, ?string $query, ?string $fragment): string
    {
        $result = '';
        if (null !== $scheme) {
            $result .= $scheme.':';
        }
        if (null !== $authority) {
            $result .= '//'.$authority;
        }
        $result .= $path;
        if (null !== $query) {
            $result .= '?'.$query;
        }
        if (null !== $fragment) {
            $result .= '#'.$fragment;
        }

        return $result;
    }
}
