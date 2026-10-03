<?php

// Keep the recursive scope: lib/shop*.php, admin/public endpoints and theme views
// must remain eligible for the explicit format:check:shop/format:fix:shop paths.
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['dist', 'uploads', 'vendor'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
