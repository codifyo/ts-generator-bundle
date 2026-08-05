<?php

namespace Codifyo\TsGeneratorBundle\Extractor;

use Codifyo\TsGeneratorBundle\Model\TypeConfig;
use Codifyo\TsGeneratorBundle\Model\TypeDefinition;

class ChainMetadataExtractor implements MetadataExtractorInterface
{
    /**
     * @param array<string, MetadataExtractorInterface> $extractors Map of modeKey => ExtractorInstance
     */
    public function __construct(
        private array $extractors = []
    ) {
    }

    public function extract(TypeConfig $typeConfig, string $fallbackMode = 'auto'): TypeDefinition
    {
        $mode = $typeConfig->getMode() ?? $fallbackMode;
        $mode = strtolower($mode);

        if ($mode !== 'auto' && isset($this->extractors[$mode])) {
            return $this->extractors[$mode]->extract($typeConfig);
        }

        // Auto Mode: Try runtime -> fallback to static -> fallback to jms
        $tryOrder = ['runtime', 'static', 'jms'];

        foreach ($tryOrder as $modeKey) {
            if (isset($this->extractors[$modeKey])) {
                $definition = $this->extractors[$modeKey]->extract($typeConfig);
                if (!empty($definition->getProperties())) {
                    return $definition;
                }
            }
        }

        return new TypeDefinition($typeConfig->getName(), $typeConfig->getClass(), [], [], $typeConfig->getKind());
    }
}
