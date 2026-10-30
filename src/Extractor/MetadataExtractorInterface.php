<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;

interface MetadataExtractorInterface
{
    public function extract(TypeConfig $typeConfig): TypeDefinition;
}
