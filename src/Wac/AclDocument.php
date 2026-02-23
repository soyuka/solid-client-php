<?php

/*
 * This file is part of the Solid Client PHP project.
 * (c) Kévin Dunglas <kevin@dunglas.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dunglas\PhpSolidClient\Wac;

final class AclDocument
{
    private const ACL_NS = 'http://www.w3.org/ns/auth/acl#';
    private const FOAF_NS = 'http://xmlns.com/foaf/0.1/';

    /**
     * @param list<Authorization> $authorizations
     */
    public function __construct(
        public readonly string $aclUrl,
        public array $authorizations = [],
    ) {
    }

    /**
     * Serializes the ACL document to Turtle format.
     */
    public function toTurtle(): string
    {
        $lines = [
            '@prefix acl: <'.self::ACL_NS.'>.',
            '@prefix foaf: <'.self::FOAF_NS.'>.',
            '',
        ];

        foreach ($this->authorizations as $i => $auth) {
            $lines[] = '<#auth'.$i.'>';
            $lines[] = '    a acl:Authorization;';

            foreach ($auth->agents as $agent) {
                $lines[] = '    acl:agent <'.$agent.'>;';
            }
            foreach ($auth->agentClasses as $class) {
                $short = self::shortenUri($class);
                $lines[] = '    acl:agentClass '.$short.';';
            }
            foreach ($auth->modes as $mode) {
                $short = self::shortenUri($mode);
                $lines[] = '    acl:mode '.$short.';';
            }

            $lines[] = '    acl:accessTo <'.$auth->accessTo.'>;';

            if ($auth->isDefault) {
                $lines[] = '    acl:default <'.$auth->accessTo.'>;';
            }

            // Replace last semicolon with period
            $lastIndex = \count($lines) - 1;
            $lines[$lastIndex] = rtrim($lines[$lastIndex], ';').'.';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Parses a Turtle ACL document using EasyRdf.
     */
    public static function fromTurtle(string $turtle, string $aclUrl, string $resourceUrl): self
    {
        \EasyRdf\RdfNamespace::set('acl', self::ACL_NS);
        \EasyRdf\RdfNamespace::set('foaf', self::FOAF_NS);

        $graph = new \EasyRdf\Graph($aclUrl, $turtle, 'turtle');
        $authorizations = [];

        foreach ($graph->allOfType('acl:Authorization') as $resource) {
            $agents = [];
            foreach ($resource->allResources('acl:agent') as $agent) {
                $agents[] = $agent->getUri();
            }

            $agentClasses = [];
            foreach ($resource->allResources('acl:agentClass') as $class) {
                $agentClasses[] = $class->getUri();
            }

            $modes = [];
            foreach ($resource->allResources('acl:mode') as $mode) {
                $modes[] = $mode->getUri();
            }

            $accessTo = $resource->getResource('acl:accessTo')?->getUri() ?? $resourceUrl;
            $isDefault = null !== $resource->getResource('acl:default');

            $authorizations[] = new Authorization($accessTo, $agents, $agentClasses, $modes, $isDefault);
        }

        return new self($aclUrl, $authorizations);
    }

    private static function shortenUri(string $uri): string
    {
        if (str_starts_with($uri, self::ACL_NS)) {
            return 'acl:'.substr($uri, \strlen(self::ACL_NS));
        }
        if (str_starts_with($uri, self::FOAF_NS)) {
            return 'foaf:'.substr($uri, \strlen(self::FOAF_NS));
        }

        return '<'.$uri.'>';
    }
}
