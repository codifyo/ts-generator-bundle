<?php

declare(strict_types=1);

namespace Codifyo\TsGeneratorBundle;

use Codifyo\TsGeneratorBundle\DependencyInjection\CodifyoTsGeneratorExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class CodifyoTsGeneratorBundle extends AbstractBundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new CodifyoTsGeneratorExtension();
    }
}
