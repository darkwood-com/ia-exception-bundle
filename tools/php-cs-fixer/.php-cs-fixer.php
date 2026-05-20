<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->ignoreVCSIgnored(true)
    ->ignoreDotFiles(false)
    ->in(dirname(__DIR__, 2))
    ->append([
        __FILE__,
    ])
;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PHP82Migration' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHPUnit100Migration:risky' => true,
        'heredoc_indentation' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_add_missing_param_annotation' => false,
        'concat_space' => ['spacing' => 'one'],
        'ordered_class_elements' => true,
        'blank_line_before_statement' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
            'import_classes' => true,
        ],
        'logical_operators' => false,
        'yoda_style' => false,
        'increment_style' => ['style' => 'post'],
        'modernize_types_casting' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;
