<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Attribute Pipelines
    |--------------------------------------------------------------------------
    |
    | Enable discovery of pipelines defined using PHP attributes.
    | Discovery should be cached in production for performance.
    |
    */

    'attributes' => [

        // Master switch for attribute-based pipelines
        'enabled' => true,

        // Paths to scan for pipeline classes with attributes
        'paths' => [
            app_path('Pipelines'),
        ],

        // Cache discovered pipelines to avoid reflection on every request
        'cache' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conflict Resolution
    |--------------------------------------------------------------------------
    |
    | What to do if multiple pipelines are registered for the same
    | (model, event) combination.
    |
    | Options:
    |  - throw            (default, safest)
    |  - prefer_fluent
    |  - prefer_attributes
    |
    */

    'conflicts' => 'throw',

    /*
    |--------------------------------------------------------------------------
    | Default Failure Behavior
    |--------------------------------------------------------------------------
    |
    | Determines how pipelines behave when a step throws an exception.
    | This can be overridden per pipeline via the fluent builder or attributes.
    |
    */

    'defaults' => [

        // Stop executing remaining steps on first failure
        'stop_on_failure' => true,

        // Optional failure handler steps (empty by default)
        'on_failure' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Step Defaults
    |--------------------------------------------------------------------------
    |
    | Default queue configuration for async pipeline steps.
    | These values are used unless overridden per step.
    |
    */

    'async' => [

        'queue' => null,   // use default queue
        'delay' => null,   // no delay
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Re-entrancy Protection
    |--------------------------------------------------------------------------
    |
    | Prevent pipelines from re-entering themselves due to model mutations
    | inside steps (e.g., saving the same model again).
    |
    */

    'reentry' => [

        // Enable basic re-entry protection
        'enabled' => true,

        // Lock TTL in seconds (should be short-lived)
        'ttl' => 10,
    ],

];
