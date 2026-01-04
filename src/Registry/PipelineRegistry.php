<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Registry;

use JacobHyde\ObserverPipeline\Definitions\PipelineDefinition;
use RuntimeException;

final class PipelineRegistry
{
    /**
     * @var array<string, array<string, PipelineDefinition>>
     *
     * Structure:
     *  [
     *    'App\Models\User' => [
     *      'created' => PipelineDefinition,
     *      'updated' => PipelineDefinition,
     *    ],
     *  ]
     */
    private array $pipelines = [];

    /**
     * @var array<string, array<string, string>>
     *
     * Tracks where the pipeline came from ("fluent" or "attributes") so we can resolve conflicts.
     */
    private array $sources = [];

    /**
     * Register a pipeline definition for a given (model,event).
     *
     * @param string $source 'fluent' | 'attributes'
     * @param string $conflictStrategy 'throw' | 'prefer_fluent' | 'prefer_attributes'
     */
    public function register(
        PipelineDefinition $definition,
        string $source = 'fluent',
        string $conflictStrategy = 'throw'
    ): void {
        $model = $definition->model;
        $event = $definition->event;

        $exists = isset($this->pipelines[$model][$event]);

        if (!$exists) {
            $this->pipelines[$model][$event] = $definition;
            $this->sources[$model][$event] = $source;

            return;
        }

        // Conflict handling
        $existingSource = $this->sources[$model][$event] ?? 'unknown';

        if ($conflictStrategy === 'throw') {
            throw new RuntimeException(
                "ObserverPipeline conflict: pipeline already registered for {$model}@{$event} (existing source: {$existingSource}, new source: {$source})."
            );
        }

        if ($conflictStrategy === 'prefer_fluent') {
            // Keep existing if it is fluent; otherwise overwrite with fluent.
            if ($existingSource === 'fluent') {
                return;
            }

            if ($source === 'fluent') {
                $this->pipelines[$model][$event] = $definition;
                $this->sources[$model][$event] = $source;
            }

            return;
        }

        if ($conflictStrategy === 'prefer_attributes') {
            // Keep existing if it is attributes; otherwise overwrite with attributes.
            if ($existingSource === 'attributes') {
                return;
            }

            if ($source === 'attributes') {
                $this->pipelines[$model][$event] = $definition;
                $this->sources[$model][$event] = $source;
            }

            return;
        }

        throw new RuntimeException(
            "ObserverPipeline invalid conflict strategy '{$conflictStrategy}'. Allowed: throw, prefer_fluent, prefer_attributes."
        );
    }

    /**
     * Retrieve the pipeline definition for a specific (model,event).
     */
    public function get(string $model, string $event): ?PipelineDefinition
    {
        return $this->pipelines[$model][$event] ?? null;
    }

    /**
     * Return all registered pipeline definitions.
     *
     * @return array<string, array<string, PipelineDefinition>>
     */
    public function all(): array
    {
        return $this->pipelines;
    }

    /**
     * Determine if a pipeline is registered for a given (model,event).
     */
    public function has(string $model, string $event): bool
    {
        return isset($this->pipelines[$model][$event]);
    }

    /**
     * Clear all registered pipelines (handy for tests).
     */
    public function clear(): void
    {
        $this->pipelines = [];
        $this->sources = [];
    }

    /**
     * Get the registration source for a given (model,event), if available.
     */
    public function source(string $model, string $event): ?string
    {
        return $this->sources[$model][$event] ?? null;
    }
}
