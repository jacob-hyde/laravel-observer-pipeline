<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests\Fixtures\Steps;

use JacobHyde\ObserverPipeline\Support\PipelineContext;

final class StepB extends OrderRecorderStep
{
    public function __invoke(PipelineContext $ctx): void
    {
        self::$calls[] = 'B';
    }
}
