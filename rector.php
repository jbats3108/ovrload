<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php85\Rector\Expression\NestedFuncCallsToPipeOperatorRector;
use Rector\Php85\Rector\StmtsAwareInterface\SequentialAssignmentsToPipeOperatorRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
    ])
    // Style is owned by Pint — keep Rector off coding-style rules so the two do not fight.
    // withPhpSets() picks PHP ^8.5 from composer.json (includes AddTypeToConstRector via php83).
    ->withPhpSets()
    ->withRules([
        NestedFuncCallsToPipeOperatorRector::class,
        SequentialAssignmentsToPipeOperatorRector::class,
    ])
    ->withTypeCoverageLevel(53)
    ->withDeadCodeLevel(53)
    ->withCodeQualityLevel(53)
    ->withImportNames()
    ->withComposerBased(phpunit: true, laravel: true);
