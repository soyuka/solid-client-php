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
 * Parses JSON-LD responses from Solid/CSS servers.
 *
 * CSS returns expanded JSON-LD by default for Accept: application/ld+json,
 * so a full JSON-LD processor is not needed — json_decode is sufficient.
 * Relative @id values should be resolved using IriHelper.
 */
final class JsonLdParser
{
    /**
     * Parses a JSON-LD string into an array of node arrays.
     *
     * Handles both single objects and arrays of objects.
     *
     * @return list<array<string, mixed>>
     */
    public static function parse(string $jsonLd): array
    {
        $decoded = json_decode($jsonLd, true, 512, \JSON_THROW_ON_ERROR);

        // If it's a single object (has @id or @type), wrap in array
        if (isset($decoded['@id']) || isset($decoded['@type'])) {
            return [$decoded];
        }

        // If it's an array of objects (expanded form)
        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [$decoded];
    }

    /**
     * Finds a node by @id in parsed JSON-LD.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, mixed>|null
     */
    public static function findById(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['@id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }
}
