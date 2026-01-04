<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Observer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Runner\PipelineRunner;

final class GenericModelObserver
{
    /**
     * Track which models have already been observed to avoid duplicate registrations.
     *
     * @var array<class-string<Model>, bool>
     */
    private static array $observedModels = [];

    public function __construct(
        private readonly PipelineRegistry $registry,
        private readonly PipelineRunner $runner
    ) {}

    /**
     * Clear observed model cache (useful for test isolation).
     */
    public static function resetObservedModels(): void
    {
        self::$observedModels = [];
    }

    /**
     * Register this observer on a given Eloquent model class.
     */
    public static function observeModel(string $modelClass, Application $app): void
    {
        if (isset(self::$observedModels[$modelClass])) {
            return;
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException(
                "ObserverPipeline: {$modelClass} must be an Eloquent model."
            );
        }

        // IMPORTANT: resolve the observer instance from *this* container
        // so it uses the same PipelineRegistry singleton as the builder.
        $modelClass::observe($app->make(self::class));

        self::$observedModels[$modelClass] = true;
    }

    /**
     * Handle a model event.
     */
    private function handleEvent(Model $model, string $event): void
    {
        if (!$this->registry->has($model::class, $event)) {
            return;
        }

        $definition = $this->registry->get($model::class, $event);

        if ($definition === null) {
            return;
        }

        $this->runner->run($definition, $model, $event);
    }

    /*
     |--------------------------------------------------------------------------
     | Eloquent Event Hooks
     |--------------------------------------------------------------------------
     */

    public function created(Model $model): void
    {
        $this->handleEvent($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->handleEvent($model, 'updated');
    }

    public function saved(Model $model): void
    {
        $this->handleEvent($model, 'saved');
    }

    public function deleted(Model $model): void
    {
        $this->handleEvent($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->handleEvent($model, 'restored');
    }
}
