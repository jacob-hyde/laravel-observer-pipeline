<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JacobHyde\ObserverPipeline\Support\PipelineContext;

final class RunPipelineStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param class-string<Model> $modelClass
     * @param mixed $modelId
     * @param class-string $stepClass
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly mixed $modelId,
        public readonly string $event,
        public readonly string $stepClass,
        public readonly array $meta = []
    ) {}

    public function handle(): void
    {
        /** @var Model|null $model */
        $model = ($this->modelClass)::query()->find($this->modelId);

        if (!$model) {
            // Model deleted or unavailable; nothing to do.
            return;
        }

        $ctx = new PipelineContext($model, $this->event);
        $ctx->setOriginal($model->getOriginal());
        $ctx->setChanges($model->getChanges());

        foreach ($this->meta as $k => $v) {
            $ctx->set((string) $k, $v);
        }

        $step = app()->make($this->stepClass);
        $step($ctx);
    }
}
