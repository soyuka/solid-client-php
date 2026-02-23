<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient;

final class ContainerEntry
{
    /**
     * @param list<string> $types RDF types of this entry
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $isContainer,
        public readonly array $types = [],
    ) {
    }
}
