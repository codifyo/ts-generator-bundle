<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Tests\Fixtures;

enum DummyStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
