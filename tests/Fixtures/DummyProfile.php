<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Types only reachable through naming conventions: snake_case serialized names mapped back to
 * camelCase properties, and untyped properties described by their is/has/can accessor.
 */
class DummyProfile
{
    #[Groups(['profile'])]
    public ?DummyTag $mainTag = null;

    #[Groups(['profile'])]
    private $active;

    #[Groups(['profile'])]
    private $children;

    #[Groups(['profile'])]
    private $edit;

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function hasChildren(): bool
    {
        return (bool) $this->children;
    }

    public function canEdit(): bool
    {
        return (bool) $this->edit;
    }
}
