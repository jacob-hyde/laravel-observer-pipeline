<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline;

use Illuminate\Contracts\Foundation\Application;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Testing\PipelineFake;

final class ObserverPipeline
{
    private static ?Application $app = null;

    /**
     * Create a pipeline builder for a given model.
     */
    public static function model(string $model): PipelineBuilder
    {
        return new PipelineBuilder(self::app(), $model);
    }

    /**
     * Discover attribute-based pipelines and register them.
     *
     * Accepts either:
     *  - array of paths / FQCNs
     *  - a single path or FQCN string
     */
    public static function discover(array|string $pathsOrClasses): void
    {
        $pathsOrClasses = is_array($pathsOrClasses) ? $pathsOrClasses : [$pathsOrClasses];

        // v1: Prefer config-driven discovery in the service provider.
        // This method is here so users can explicitly trigger discovery when needed.
        /** @var \JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer $discoverer */
        $discoverer = self::app()->make(\JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer::class);

        // If you later enhance the discoverer to accept explicit classes/paths, wire that here.
        // For now, it relies on its configured paths.
        $discoverer->discoverAndRegister(
            self::app()->make(PipelineRegistry::class),
            (string) config('observer-pipeline.conflicts', 'throw')
        );
    }

    /**
     * Enable test faking utilities.
     *
     * This returns the fake so tests can call assertions, e.g.:
     *   ObserverPipeline::fake()->assertStepQueued(...)
     */
    public static function fake(): PipelineFake
    {
        /** @var PipelineFake $fake */
        $fake = self::app()->make(PipelineFake::class);

        // Mark fake as active for the duration of the test run.
        $fake->activate();

        return $fake;
    }

    /**
     * Resolve the Laravel container application instance.
     */
    private static function app(): Application
    {
        if (self::$app instanceof Application) {
            return self::$app;
        }

        // In normal Laravel usage, app() exists.
        if (function_exists('app')) {
            /** @var Application $app */
            $app = app();
            self::$app = $app;

            return $app;
        }

        throw new \RuntimeException('ObserverPipeline could not resolve the Laravel application container.');
    }

    /**
     * For advanced usage (or testbench edge cases), you can inject an app container.
     */
    public static function setApplication(Application $app): void
    {
        self::$app = $app;
    }
}
