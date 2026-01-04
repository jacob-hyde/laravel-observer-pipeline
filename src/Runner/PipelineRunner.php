<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Runner;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use JacobHyde\ObserverPipeline\Definitions\PipelineDefinition;
use JacobHyde\ObserverPipeline\Jobs\RunPipelineStepJob;
use JacobHyde\ObserverPipeline\Registry\PipelineRegistry;
use JacobHyde\ObserverPipeline\Support\PipelineContext;
use JacobHyde\ObserverPipeline\Testing\PipelineFake;

final class PipelineRunner
{
    public function __construct(
        private readonly PipelineRegistry $registry,
        private readonly Application $app
    ) {}

    /**
     * Execute the pipeline steps for a model event.
     *
     * Fail loudly semantics:
     * - stopOnFailure = true  => throw immediately on first failure
     * - stopOnFailure = false => continue running steps, then throw the first failure at the end
     */
    public function run(PipelineDefinition $definition, Model $model, string $event): void
    {
        $ctx = new PipelineContext($model, $event);

        // Update-ish event data (safe even if empty)
        $ctx->setOriginal($model->getOriginal());
        $ctx->setChanges($model->getChanges());

        $firstFailure = null;

        foreach ($definition->steps as $stepClass) {
            // If tests have faked the pipeline, just record and skip real execution.
            if ($this->isFaked()) {
                $this->fake()->recordRan($model::class, $event, $stepClass);

                if ($this->isAsyncStep($definition, $stepClass)) {
                    $this->fake()->recordQueued($stepClass);
                }

                continue;
            }

            if ($this->isAsyncStep($definition, $stepClass)) {
                $this->dispatchAsyncStep($definition, $model, $event, $stepClass, $ctx);
                continue;
            }

            try {
                $this->runStep($stepClass, $ctx);
            } catch (\Throwable $e) {
                $this->runFailureHandlersBestEffort($definition, $ctx, $e);

                // Always "fail loudly", but allow continueOnFailure to keep running steps
                $firstFailure ??= $e;

                if ($definition->stopOnFailure) {
                    throw $e;
                }
            }
        }

        // If continueOnFailure was used and at least one step failed, still fail loudly at the end.
        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }

    private function runStep(string $stepClass, PipelineContext $ctx): void
    {
        $step = $this->app->make($stepClass);

        // v1: invokable step classes
        $step($ctx);
    }

    private function dispatchAsyncStep(
        PipelineDefinition $definition,
        Model $model,
        string $event,
        string $stepClass,
        PipelineContext $ctx
    ): void {
        $options = $definition->async[$stepClass] ?? [];

        $job = new RunPipelineStepJob(
            modelClass: $model::class,
            modelId: $model->getKey(),
            event: $event,
            stepClass: $stepClass,
            meta: $ctx->meta()
        );

        $pending = dispatch($job);

        if (!empty($options['connection'])) {
            $pending->onConnection($options['connection']);
        }

        if (!empty($options['queue'])) {
            $pending->onQueue($options['queue']);
        }

        if (array_key_exists('delay', $options) && $options['delay'] !== null) {
            $pending->delay((int) $options['delay']);
        }
    }

    private function runFailureHandlersBestEffort(PipelineDefinition $definition, PipelineContext $ctx, \Throwable $e): void
    {
        // Best-effort failure steps. Do not let these mask the original failure.
        foreach ($definition->onFailureSteps as $failureStepClass) {
            try {
                $this->runStep($failureStepClass, $ctx);
            } catch (\Throwable) {
                // ignore
            }
        }

        // Optional: if you want the failure available to failure steps / later inspection
        $ctx->set('_exception', $e);
    }

    private function isAsyncStep(PipelineDefinition $definition, string $stepClass): bool
    {
        return array_key_exists($stepClass, $definition->async);
    }

    private function isFaked(): bool
    {
        if (!$this->app->bound(PipelineFake::class)) {
            return false;
        }

        /** @var PipelineFake $fake */
        $fake = $this->app->make(PipelineFake::class);

        return $fake->isActive();
    }

    private function fake(): PipelineFake
    {
        /** @var PipelineFake $fake */
        $fake = $this->app->make(PipelineFake::class);

        return $fake;
    }
}
