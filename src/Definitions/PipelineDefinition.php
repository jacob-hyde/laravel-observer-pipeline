<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Definitions;

final class PipelineDefinition
{
    /**
     * @param array<int, class-string> $steps
     * @param array<class-string, array<string, mixed>> $async
     * @param array<int, class-string> $onFailureSteps
     */
    public function __construct(
        public readonly string $model,
        public readonly string $event,
        public readonly array $steps,
        public readonly array $async = [],
        public readonly bool $stopOnFailure = true,
        public readonly array $onFailureSteps = [],
    ) {}
}
