<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient;

use Dunglas\PhpSolidClient\Wac\AclDocument;
use EasyRdf\Graph;
use ML\IRI\IRI;
use ML\JsonLD\JsonLD;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kévin Dunglas <kevin@dunglas.fr>
 */
final class SolidClient
{
    private const DEFAULT_MIME_TYPE = 'text/turtle';
    private const LDP_BASIC_CONTAINER = 'http://www.w3.org/ns/ldp#BasicContainer';
    private const LDP_RESOURCE = 'http://www.w3.org/ns/ldp#Resource';
    private const OIDC_ISSUER = 'http://www.w3.org/ns/solid/terms#oidcIssuer';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?OidcClient $oidcClient = null,
    ) {
    }

    public function createContainer(string $parentUrl, string $name, ?string $data = null): ResponseInterface
    {
        return $this->post($parentUrl, $data, $name, true);
    }

    /**
     * Creates a new resource by performing a Solid/LDP POST operation to a specified container.
     *
     * @see https://github.com/solid/solid-web-client/blob/main/src/client.js#L231=
     */
    public function post(string $url, ?string $data = null, ?string $slug = null, bool $isContainer = false, array $options = []): ResponseInterface
    {
        if ($isContainer || !isset($options['headers']['Content-Type'])) {
            $options['headers']['Content-Type'] = self::DEFAULT_MIME_TYPE;
        }
        if (null !== $data) {
            $options['body'] = $data;
        }
        if (null !== $slug) {
            $options['headers']['Slug'] = $slug;
        }

        $options['headers']['Link'] = \sprintf('<%s>; rel="type"', $isContainer ? self::LDP_BASIC_CONTAINER : self::LDP_RESOURCE);

        return $this->request('POST', $url, $options);
    }

    public function put(string $url, ?string $data = null, bool $isContainer = false, array $options = []): ResponseInterface
    {
        if (!isset($options['headers']['Content-Type'])) {
            $options['headers']['Content-Type'] = self::DEFAULT_MIME_TYPE;
        }
        if (null !== $data) {
            $options['body'] = $data;
        }
        if ($isContainer) {
            $options['headers']['Link'] = \sprintf('<%s>; rel="type"', self::LDP_BASIC_CONTAINER);
        }

        return $this->request('PUT', $url, $options);
    }

    public function get(string $url, array $options = []): ResponseInterface
    {
        return $this->request('GET', $url, $options);
    }

    public function head(string $url, array $options = []): ResponseInterface
    {
        return $this->request('HEAD', $url, $options);
    }

    public function delete(string $url, array $options = []): ResponseInterface
    {
        return $this->request('DELETE', $url, $options);
    }

    public function patch(string $url, string $data, string $contentType = 'application/sparql-update', array $options = []): ResponseInterface
    {
        $options['headers']['Content-Type'] = $contentType;
        $options['body'] = $data;

        return $this->request('PATCH', $url, $options);
    }

    public function getResourceMetadata(string $url, array $options = []): ResourceMetadata
    {
        $response = $this->head($url, $options);

        return ResourceMetadata::fromResponseHeaders($response->getHeaders(false));
    }

    /**
     * Lists the contents of an LDP container by parsing ldp:contains from JSON-LD.
     *
     * @return list<ContainerEntry>
     */
    public function getContainerContents(string $url, array $options = []): array
    {
        $options['headers']['Accept'] = 'application/ld+json';
        $response = $this->get($url, $options);
        $decoded = json_decode($response->getContent());
        $expanded = JsonLD::expand($decoded, ['base' => $url]);

        $entries = [];
        foreach ($expanded as $node) {
            $contains = $node->{'http://www.w3.org/ns/ldp#contains'} ?? [];
            if ([] === $contains || !$contains) {
                continue;
            }

            foreach ($contains as $entry) {
                $entryUrl = $entry->{'@id'} ?? null;
                if (null === $entryUrl) {
                    continue;
                }
                $types = $entry->{'@type'} ?? [];
                $isContainer = [] !== array_filter($types, ResourceMetadata::isContainerType(...))
                    || str_ends_with($entryUrl, '/');

                $entries[] = new ContainerEntry($entryUrl, $isContainer, $types);
            }
        }

        return $entries;
    }

    /**
     * Ensures that an LDP container exists at the given URL, creating it (and any missing parents) if necessary.
     */
    public function ensureContainerExists(string $url, array $options = []): void
    {
        if (!str_ends_with($url, '/')) {
            $url .= '/';
        }

        try {
            $this->head($url, $options)->getHeaders();

            return;
        } catch (ClientExceptionInterface $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }

        // Ensure parent exists first
        $parentUrl = self::getParentContainerUrl($url);
        if (null !== $parentUrl && $parentUrl !== $url) {
            $this->ensureContainerExists($parentUrl, $options);
        }

        $this->put($url, null, true, $options);
    }

    /**
     * Recursively walks an LDP container tree, yielding ContainerEntry objects.
     *
     * @param int $maxDepth -1 for unlimited, 0 for current level only
     *
     * @return \Generator<int, ContainerEntry>
     */
    public function walkContainer(string $url, int $maxDepth = -1, array $options = []): \Generator
    {
        $entries = $this->getContainerContents($url, $options);

        foreach ($entries as $entry) {
            yield $entry;

            if ($entry->isContainer && 0 !== $maxDepth) {
                yield from $this->walkContainer(
                    $entry->url,
                    -1 === $maxDepth ? -1 : $maxDepth - 1,
                    $options,
                );
            }
        }
    }

    private static function getParentContainerUrl(string $url): ?string
    {
        $trimmed = rtrim($url, '/');
        $lastSlash = strrpos($trimmed, '/');
        if (false === $lastSlash) {
            return null;
        }

        $parent = substr($trimmed, 0, $lastSlash + 1);
        if (preg_match('#^https?://[^/]+/$#', $parent)) {
            return null;
        }

        return $parent;
    }

    /**
     * Fetches and parses the ACL document for a resource.
     *
     * Discovers the .acl URL via the Link header (rel="acl") from a HEAD request.
     */
    public function getAcl(string $resourceUrl, array $options = []): AclDocument
    {
        $metadata = $this->getResourceMetadata($resourceUrl, $options);
        if (null === $metadata->aclUrl) {
            throw new Exception(\sprintf('No ACL URL found for resource "%s"', $resourceUrl));
        }

        $aclUrl = (string) (new IRI($resourceUrl))->resolve($metadata->aclUrl);
        $response = $this->get($aclUrl, array_merge($options, [
            'headers' => ['Accept' => 'text/turtle'],
        ]));

        return AclDocument::fromTurtle($response->getContent(), $aclUrl, $resourceUrl);
    }

    /**
     * Writes an ACL document for a resource.
     */
    public function putAcl(string $resourceUrl, AclDocument $acl, array $options = []): ResponseInterface
    {
        return $this->put($acl->aclUrl, $acl->toTurtle(), false, array_merge($options, [
            'headers' => ['Content-Type' => 'text/turtle'],
        ]));
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($accessToken = $this->oidcClient?->getAccessToken()) {
            $options['headers']['Authorization'] = 'DPoP '.$accessToken;
            $options['headers']['DPoP'] = $this->oidcClient->createDPoP($method, $url, true);
        }

        return $this->httpClient->request($method, $url, $options);
    }

    public function getProfile(string $webId, array $options = []): Graph
    {
        $response = $this->get($webId, $options);
        if (null !== $format = $response->getHeaders()['content-type'][0] ?? null) {
            // strip parameters (such as charset) if any
            $format = explode(';', $format, 2)[0];
        }

        return new Graph($webId, $response->getContent(), $format);
    }

    public function getOidcIssuer(string $webId, array $options = []): string
    {
        $graph = $this->getProfile($webId, $options);

        $issuer = $graph->get($webId, \sprintf('<%s>', self::OIDC_ISSUER))?->getUri();
        if (!\is_string($issuer)) {
            throw new Exception('Unable to find the OIDC issuer associated with this WebID', 1);
        }

        return $issuer;
    }
}
