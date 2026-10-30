<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Properties without usable type information: only normalized values can describe them.
 */
class DummyReport
{
    #[Groups(['report'])]
    private int $id;

    /** Untyped on purpose */
    #[Groups(['report'])]
    private $stats;

    #[Groups(['report'])]
    private array $history;

    /**
     * @param array<string, mixed>       $stats
     * @param list<array<string, mixed>> $history
     */
    public function __construct(int $id, array $stats, array $history)
    {
        $this->id = $id;
        $this->stats = $stats;
        $this->history = $history;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStats()
    {
        return $this->stats;
    }

    public function getHistory(): array
    {
        return $this->history;
    }
}
