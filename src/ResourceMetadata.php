<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient;

final class ResourceMetadata
{
    /**
     * @param array<string, list<string>> $wacAllow parsed WAC-Allow header (e.g. ['user' => ['read', 'write'], 'public' => ['read']])
     */
    public function __construct(
        public readonly ?string $contentType = null,
        public readonly ?int $contentLength = null,
        public readonly ?\DateTimeImmutable $lastModified = null,
        public readonly ?string $ldpType = null,
        public readonly array $wacAllow = [],
        public readonly ?string $aclUrl = null,
    ) {
    }

    public function isContainer(): bool
    {
        return 'http://www.w3.org/ns/ldp#BasicContainer' === $this->ldpType
            || 'http://www.w3.org/ns/ldp#Container' === $this->ldpType;
    }

    /**
     * @param array<string, list<string>> $responseHeaders normalized response headers
     */
    public static function fromResponseHeaders(array $responseHeaders): self
    {
        $contentType = isset($responseHeaders['content-type'][0])
            ? explode(';', $responseHeaders['content-type'][0], 2)[0]
            : null;

        $contentLength = isset($responseHeaders['content-length'][0])
            ? (int) $responseHeaders['content-length'][0]
            : null;

        $lastModified = isset($responseHeaders['last-modified'][0])
            ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $responseHeaders['last-modified'][0]) ?: null
            : null;

        $ldpType = null;
        $aclUrl = null;
        foreach ($responseHeaders['link'] ?? [] as $linkHeader) {
            foreach (explode(',', $linkHeader) as $link) {
                $link = trim($link);
                if (preg_match('/<([^>]+)>;\s*rel="type"/', $link, $matches)) {
                    if (str_contains($matches[1], 'ldp#')) {
                        $ldpType = $matches[1];
                    }
                }
                if (preg_match('/<([^>]+)>;\s*rel="acl"/', $link, $matches)) {
                    $aclUrl = $matches[1];
                }
            }
        }

        $wacAllow = [];
        if (isset($responseHeaders['wac-allow'][0])) {
            // Format: user="read write append control",public="read"
            if (preg_match_all('/(\w+)="([^"]*)"/', $responseHeaders['wac-allow'][0], $matches, \PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $wacAllow[$match[1]] = array_filter(explode(' ', $match[2]));
                }
            }
        }

        return new self($contentType, $contentLength, $lastModified, $ldpType, $wacAllow, $aclUrl);
    }
}
