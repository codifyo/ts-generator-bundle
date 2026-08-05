<?php

namespace Codifyo\TsGeneratorBundle;

use Codifyo\TsGeneratorBundle\DependencyInjection\CodifyoTsGeneratorExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CodifyoTsGeneratorBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new CodifyoTsGeneratorExtension();
    }
}
