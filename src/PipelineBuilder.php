<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use JacobHyde\ObserverPipeline\Definitions\PipelineDefinition;
use JacobHyde\ObserverPipeline\Observer\GenericModelObserver;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;

final class PipelineBuilder
{
    private ?string $event = null;

    /**
     * @var array<int, class-string>
     */
    private array $steps = [];

    /**
     * @var array<class-string, array<string, mixed>>
     */
    private array $async = [];

    private bool $stopOnFailure = true;

    /**
     * @var array<int, class-string>
     */
    private array $onFailureSteps = [];

    public function __construct(
        private readonly Application $app,
        private readonly string $model
    ) {}

    public function on(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @param array<int, class-string> $steps
     */
    public function pipe(array $steps): self
    {
        $this->steps = array_values($steps);

        return $this;
    }

    /**
     * Mark steps to be executed asynchronously.
     *
     * Example:
     *  ->async([
     *      SendWelcomeEmail::class => ['queue' => 'emails', 'delay' => 10],
     *  ])
     *
     * @param array<class-string, array<string, mixed>> $stepOptions
     */
    public function async(array $stepOptions): self
    {
        $this->async = $stepOptions;

        return $this;
    }

    public function stopOnFailure(): self
    {
        $this->stopOnFailure = true;

        return $this;
    }

    public function continueOnFailure(): self
    {
        $this->stopOnFailure = false;

        return $this;
    }

    /**
     * @param array<int, class-string> $steps
     */
    public function onFailure(array $steps): self
    {
        $this->onFailureSteps = array_values($steps);

        return $this;
    }

    public function register(): void
    {
        if (!$this->event) {
            throw new \InvalidArgumentException(
                'ObserverPipeline: event is required. Call ->on(\'created\') (or similar) before ->register().'
            );
        }

        if ($this->steps === []) {
            throw new \InvalidArgumentException(
                'ObserverPipeline: steps are required. Call ->pipe([...]) before ->register().'
            );
        }

        $definition = new PipelineDefinition(
            model: $this->model,
            event: $this->event,
            steps: $this->steps,
            async: $this->normalizeAsyncOptions($this->async),
            stopOnFailure: $this->stopOnFailure,
            onFailureSteps: $this->onFailureSteps
        );

        /** @var PipelineRegistry $registry */
        $registry = $this->app->make(PipelineRegistry::class);

        $registry->register(
            $definition,
            'fluent',
            $this->getConfigString('observer-pipeline.conflicts', 'throw')
        );

        $this->ensureModelObserverIsRegistered($this->model);
    }

    private function ensureModelObserverIsRegistered(string $model): void
    {
        // Register a single generic observer per model class. We guard using a static set
        // in GenericModelObserver to avoid duplicate observe() calls.
        GenericModelObserver::observeModel($model, $this->app);
    }

    /**
     * @param array<class-string, array<string, mixed>> $async
     * @return array<class-string, array<string, mixed>>
     */
    private function normalizeAsyncOptions(array $async): array
    {
        $defaults = (array) $this->getConfigValue('observer-pipeline.async', []);

        $normalized = [];
        foreach ($async as $stepClass => $options) {
            if (!is_array($options)) {
                $options = [];
            }

            $normalized[$stepClass] = array_filter(
                array_merge($defaults, $options),
                static fn ($v) => $v !== null
            );
        }

        return $normalized;
    }

    private function getConfigString(string $key, string $default): string
    {
        $value = $this->getConfigValue($key, $default);

        return is_string($value) ? $value : $default;
    }

    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        // Preferred: resolve from container when available
        try {
            if (method_exists($this->app, 'bound') && $this->app->bound('config')) {
                /** @var ConfigRepository $config */
                $config = $this->app->make('config');

                return $config->get($key, $default);
            }
        } catch (\Throwable) {
            // ignore and fall through
        }

        // Fallback: helper
        if (function_exists('config')) {
            try {
                return config($key, $default);
            } catch (\Throwable) {
                // ignore and fall through
            }
        }

        // Absolute fallback
        return $default;
    }
}
