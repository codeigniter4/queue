<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Queue.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Preset\Presets\Psr4Preset;
use Boundwize\StructArmed\Rule\Rules\Class_\MustBeFinalRule;

return Architecture::define()
    ->rule(
        'tests.must_be_final',
        new MustBeFinalRule(layer: 'tests'),
    )
    ->skip([
        Psr4Preset::CLASSES_MUST_MATCH_COMPOSER => [
            __DIR__ . '/src/Database/Migrations',
        ],
    ])
    ->cacheDirectory(is_dir('/tmp') ? '/tmp/structarmed' : null)
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())
    ->layer('tests', __DIR__ . '/tests')
    ->layerPattern('Model', '/^CodeIgniter\\\\Queue\\\\.*Model$/')
    ->layerPattern('Controller', '/^CodeIgniter\\\\Queue\\\\Controllers\\\\.*$/')
    ->layerPattern('Config', '/^CodeIgniter\\\\Queue\\\\Config\\\\.*$/', '/^.*Services$/')
    ->layerPattern('Entity', '/^CodeIgniter\\\\Queue\\\\Entities\\\\.*$/')
    ->layerPattern('Service', '/^.*Services$/')
    ->ruleset([
        'Entity'  => ['Config', 'Model', 'Service'],
        'Config'  => ['Service'],
        'Model'   => ['Config', 'Entity', 'Service'],
        'Service' => ['Config'],
    ]);
