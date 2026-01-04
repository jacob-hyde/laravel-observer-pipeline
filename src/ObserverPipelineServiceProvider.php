<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline;

use Illuminate\Support\ServiceProvider;
use JacobHyde\ObserverPipeline\Console\CachePipelinesCommand;
use JacobHyde\ObserverPipeline\Console\ClearPipelinesCacheCommand;
use JacobHyde\ObserverPipeline\Console\ListPipelinesCommand;
use JacobHyde\ObserverPipeline\Discovery\AttributeDiscoverer;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Runner\PipelineRunner;
use JacobHyde\ObserverPipeline\Testing\PipelineFake;

final class ObserverPipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/observer-pipeline.php', 'observer-pipeline');

        $this->app->singleton(PipelineRegistry::class, function () {
            return new PipelineRegistry();
        });

        $this->app->singleton(PipelineRunner::class, function ($app) {
            return new PipelineRunner(
                $app->make(PipelineRegistry::class),
                $app
            );
        });

        $this->app->bind(AttributeDiscoverer::class, function ($app) {
            return new AttributeDiscoverer(
                $app['files'],
                $app['config']->get('observer-pipeline.attributes.paths', []),
                $app['config']->get('observer-pipeline.attributes.cache', true),
                $app->bootstrapPath('cache/observer-pipeline.php')
            );
        });

        $this->app->singleton(PipelineFake::class, function () {
            return new PipelineFake();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/observer-pipeline.php' => $this->configPath(),
        ], 'observer-pipeline-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListPipelinesCommand::class,
                CachePipelinesCommand::class,
                ClearPipelinesCacheCommand::class,
            ]);
        }

        // Attribute discovery (safe: uses cached manifest if enabled)
        $attributesEnabled = (bool) config('observer-pipeline.attributes.enabled', false);

        if ($attributesEnabled) {
            // Discover will register any attribute pipelines into the same registry.
            // This should be cheap in prod when cache is enabled.
            $this->app->make(AttributeDiscoverer::class)->discoverAndRegister(
                $this->app->make(PipelineRegistry::class),
                config('observer-pipeline.conflicts', 'throw')
            );
        }
    }

    private function configPath(): string
    {
        // Support both Laravel app() helper and base_path() edge cases in testbench
        return function_exists('config_path')
            ? config_path('observer-pipeline.php')
            : $this->app->basePath('config/observer-pipeline.php');
    }
}
