<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Discovery;

use JacobHyde\ObserverPipeline\Definitions\PipelineDefinition;

final class PipelineManifest
{
    /**
     * @param array<int, PipelineDefinition> $definitions
     * @return array<int, array<string, mixed>>
     */
    public static function serialize(array $definitions): array
    {
        return array_map(static function (PipelineDefinition $def): array {
            return [
                'model' => $def->model,
                'event' => $def->event,
                'steps' => $def->steps,
                'async' => $def->async,
                'stop_on_failure' => $def->stopOnFailure,
                'on_failure' => $def->onFailureSteps,
            ];
        }, $definitions);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @return array<int, PipelineDefinition>
     */
    public static function hydrate(array $data): array
    {
        return array_map(static function (array $row): PipelineDefinition {
            return new PipelineDefinition(
                model: (string) $row['model'],
                event: (string) $row['event'],
                steps: (array) ($row['steps'] ?? []),
                async: (array) ($row['async'] ?? []),
                stopOnFailure: (bool) ($row['stop_on_failure'] ?? true),
                onFailureSteps: (array) ($row['on_failure'] ?? [])
            );
        }, $data);
    }
}
