<?php

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    // Grouped / aliased / function imports are the subject of this fixture: they must not be normalized
    ->notPath('Fixtures/DummyEntityWithGroupedImports.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
        'phpdoc_to_comment' => false,
        'global_namespace_import' => false,
    ])
    ->setFinder($finder)
;
