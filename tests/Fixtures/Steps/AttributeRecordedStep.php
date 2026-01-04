<?php

declare(strict_types=1);

namespace JacobHyde\ObserverPipeline\Tests\Fixtures\Steps;

use JacobHyde\ObserverPipeline\Support\PipelineContext;

final class AttributeRecordedStep
{
    public static bool $ran = false;

    public static function reset(): void
    {
        self::$ran = false;
    }

    public function __invoke(PipelineContext $ctx): void
    {
        self::$ran = true;
    }
}
