<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Wac;

final class Authorization
{
    public const MODE_READ = 'http://www.w3.org/ns/auth/acl#Read';
    public const MODE_WRITE = 'http://www.w3.org/ns/auth/acl#Write';
    public const MODE_APPEND = 'http://www.w3.org/ns/auth/acl#Append';
    public const MODE_CONTROL = 'http://www.w3.org/ns/auth/acl#Control';

    public const AGENT_CLASS_PUBLIC = 'http://xmlns.com/foaf/0.1/Agent';
    public const AGENT_CLASS_AUTHENTICATED = 'http://www.w3.org/ns/auth/acl#AuthenticatedAgent';

    /**
     * @param list<string> $agents       WebID URIs
     * @param list<string> $agentClasses foaf:Agent (public) or acl:AuthenticatedAgent
     * @param list<string> $modes        acl:Read, acl:Write, acl:Append, acl:Control
     */
    public function __construct(
        public string $accessTo,
        public array $agents = [],
        public array $agentClasses = [],
        public array $modes = [],
        public bool $isDefault = false,
    ) {
    }
}
